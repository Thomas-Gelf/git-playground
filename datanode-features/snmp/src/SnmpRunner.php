<?php

namespace IcingaFeature\Snmp;

use Amp\Redis\RedisClient;
use gipfl\Json\JsonString;
use IcingaDataNode\Events;
use IcingaDataNode\Monitoring\Measurement;
use IcingaDataNode\NodeIdentifier;
use IcingaDataNode\Redis\RedisTables;
use IcingaDataNode\Services;
use IcingaFeature\Snmp\DataStructure\DbTable;
use IcingaFeature\Snmp\Scenario\PollSysInfo;
use IcingaFeature\Snmp\Scenario\ScenarioResultHandler;
use IcingaFeature\Snmp\SnmpScenario\KnownTargetsHealth;
use IcingaFeature\Snmp\SnmpScenario\SnmpTargets;
use IcingaFeature\Snmp\SnmpScenario\TargetState;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;
use React\Promise\PromiseInterface;
use Revolt\EventLoop;
use Throwable;

use function React\Promise\resolve;

class SnmpRunner
{
    /** @var ?PeriodicScenarioRunner[] */
    protected array $periodicScenarios = [];
    protected ?RedisTables $redisTables = null;
    protected bool $shuttingDown = false;
    protected ?RedisClient $redisClientForMetrics = null;

    public function __construct(
        public readonly NodeIdentifier $nodeIdentifier,
        protected readonly LoggerInterface $logger,
        public readonly Events $events,
        public readonly Services $services,
        public SnmpCredentials $credentials = new SnmpCredentials([]),
        public SnmpTargets $targets = new SnmpTargets(),
        public KnownTargetsHealth $health = new KnownTargetsHealth(),
    ) {}

    public function run(): void
    {
        $this->waitForRedisTables();
        EventLoop::queue($this->prepareInternalMetrics(...));
    }

    protected function prepareInternalMetrics(): void
    {
        $this->redisClientForMetrics = $this->services->getNewRedisClient('snmp/internalMetrics');
        $this->logger->notice('Redis connection for internal metrics is ready');
    }

    public function stop(): PromiseInterface
    {
        $this->shuttingDown = true;
        foreach ($this->periodicScenarios as $scenario) {
            $scenario->stop();
        }
        $this->periodicScenarios = [];

        return resolve(null);
    }

    protected function setRedisTables(?RedisTables $tables): void
    {
        $this->redisTables = $tables;
    }

    protected function waitForRedisTables(): void
    {
        $this->logger->debug('SNMP Runner is waiting for redis tables');
        $this->services->getRedisTables('snmp/runner')->then(function (RedisTables $tables) {
            $this->logger->notice('SNMP runner got redis tables');
            $this->setRedisTables($tables);
            $tables->on('close', function () {
                $this->setRedisTables(null);
                if (! $this->shuttingDown) {
                    EventLoop::queue($this->waitForRedisTables(...));
                }
            });
            $this->logger->notice('RPC Context SNMP got redis tables, done');
        }, function (\Exception $e) {
            $this->logger->error('NO REDIS TABLES, retrying in 10s: ' . $e->getMessage());
            EventLoop::delay(10, $this->waitForRedisTables(...));
        });
    }

    public function launchPeriodicScenarios(array $scenarioClasses): void
    {
        foreach ($scenarioClasses as $class) {
            $this->stopPeriodicScenario($class);
        }
        if (empty($this->targets->targets)) {
            $this->logger->notice('Got no targets');
            return;
        }
        foreach ($scenarioClasses as $class) {
            $this->launchPeriodicScenario($class);
        }
    }

    protected function stopPeriodicScenario(string $class): void
    {
        if (isset($this->periodicScenarios[$class])) {
            $this->logger->notice("Stopping periodic scenario runner instance for $class");
            $this->periodicScenarios[$class]->stop();
            unset($this->periodicScenarios[$class]);
        }
    }

    protected function shipScenarioMeasurement(Measurement $measurement): void
    {
        EventLoop::queue(function () use ($measurement) {
            $this->redisClientForMetrics?->execute(
                'XADD',
                'internalMetrics',
                'MAXLEN',
                '~',
                10_000,
                '*',
                'measurement',
                JsonString::encode($measurement)
            );
        });
    }

    protected function launchPeriodicScenario(string $class): void
    {
        $scenario = new PeriodicScenario($class, $this->targets, $this->logger);
        $this->periodicScenarios[$class] = $runner = new PeriodicScenarioRunner($this, $scenario, $this->logger);
        $runner->on(PeriodicScenarioRunner::ON_MEASUREMENT, $this->shipScenarioMeasurement(...));
        $runner->on(PeriodicScenarioRunner::ON_RESULT, function (Result $result) use ($scenario) {
            // $this->logger->notice($scenario->name . ' shipped a result');
            try {
                $this->processResult($result, $scenario);
            } catch (Throwable $e) {
                $this->logger->error('Processing result failed: ' . $e->getMessage());
            }
        });
        $runner->start();
    }

    protected function processResult(Result $result, PeriodicScenario $scenario): void
    {
        $target = $result->target;
        if ($result->succeeded()) {
            /*
            $this->logger->notice(sprintf(
                'Got scenario result: %s (%s)',
                $target->address->ip,
                $scenario->scenarioClass
            ));
            */
            // $target points to our target object, it's state is still the former one!
            if ($target->state !== TargetState::REACHABLE && $scenario->scenarioClass === PollSysInfo::class) {
                $formerState = $target->state;
                $target->state = TargetState::REACHABLE;
                // TODO: emit db update -> state
                // TODO: $result->target->error = null;

                $this->logger->notice(sprintf(
                    'Target was %s, and is now reachable: %s (%s)',
                    $formerState->value,
                    $target->address->ip,
                    $scenario->scenarioClass
                ));
                $this->redisTables->setTableEntry('snmp_target_health', $target->identifier, [
                    'uuid'
                ], [
                    'uuid'  => $target->identifier,
                    'state' => TargetState::REACHABLE->value,
                ]);
                // TODO: emit db update -> state
                // TODO: $result->target->error = $result->error;
            }

            $deviceUuid = RamseyUuid::fromString($result->target->identifier);
            if ($scenario->requestType === 'get') {
                // $this->logger->notice('Processing GET');
                $scenario->processResult($result->target, $result->result);
                try {
                    $this->sendResultToRedis(
                        $scenario->resultHandler,
                        $scenario->resultHandler->getResultObjectInstance(
                            $this->nodeIdentifier->uuid,
                            $deviceUuid,
                            $result->result
                        )
                    );
                } catch (Throwable $e) {
                    $this->logger->error('GET Failed: ' . $e->getMessage());
                }
            } else {
                // walk
                $instances = [];
                $resultHandler = $scenario->resultHandler;
                // $this->logger->notice('Result: ' . var_export($result->result, 1));
                try {
                foreach ($result->result as $r) {
                        $instances[] = $resultHandler->getResultObjectInstance(
                            $this->nodeIdentifier->uuid,
                            $deviceUuid,
                            $r
                        );
                }
                } catch (Throwable $e) {
                    $this->logger->error(sprintf(
                        'Failed to instantiate %s for %s (%s): %s -> %s',
                        $scenario->name,
                        $scenario->resultHandler->getDbTable()->tableName,
                        $e->getMessage(),
                        print_r($r, 1),
                        JsonString::encode($result)
                    ));
                    return;
                }
                // $this->logger->notice('Instances: ' . var_export($instances, 1));
                if ($dbTable = $resultHandler->getDbTable()) {
                    $tables = [];
                    try {
                        foreach ($instances as $instance) {
                            $tables[$resultHandler->getDbUpdateKey($instance)] = $resultHandler->getInstanceDbProperties($instance);
                        }
                        $this->sendTableEntries($dbTable, $deviceUuid, array_keys($dbTable->keyProperties), $tables);
                    } catch (\Throwable $e) {
                        $this->logger->error('Sending table updates failed: ' . $e->getMessage() . $e->getFile() . $e->getLine());
                    }
                } else {
                    $this->logger->notice('Scenario ' . $scenario->name . ' has no DB table');
                }
                try {
                    if ($measurements = $resultHandler->prepareMeasurements($deviceUuid, $instances)) {
                        $this->logger->notice('Measurements: ' . JsonString::encode($measurements));
                        $this->events->emit('measurements', [$measurements]);
                    }
                } catch (\Throwable $e) {
                    $this->logger->notice($e->getMessage());
                }

            }
        } else {
            $this->logger->notice(sprintf(
                'Scenario failed: %s (%s)',
                $target->identifier,
                $scenario->scenarioClass
            ));
            if ($result->target->state !== TargetState::FAILING && $scenario->scenarioClass === PollSysInfo::class) {
                $this->logger->notice(sprintf(
                    'Scenario failed, setting failing: %s (%s)',
                    $target->identifier,
                    $scenario->scenarioClass
                ));

                $result->target->state = TargetState::FAILING;
                $this->redisTables->setTableEntry('snmp_target_health', $result->target->identifier, [
                    'uuid'
                ], [
                    'uuid'  => $result->target->identifier,
                    'state' => TargetState::FAILING->value,
                ]);
                // TODO: emit db update -> state
                // TODO: $result->target->error = $result->error;
            }
        }

        if ($scenario->scenarioClass === PollSysInfo::class) {
            $this->health->setCurrentResult($result->target->identifier, $result);
        }
        /*
        if ($result->succeeded()) {
            try {
                printf("OK %s: %s\n", $result->target->address->ip, self::octetString($result->result['sys_descr']));
            } catch (\Throwable $e) {
                echo $e->getMessage();
            }
        } else {
            printf("ERR %s: %s\n", $result->target->address->ip, $result->error);
        }
        */
        // echo JsonString::encode($result, JSON_PRETTY_PRINT) . "\n";
    }

    protected function sendTableEntries(
        DbTable $dbTable,
        UuidInterface $deviceUuid,
        array $keyProperties,
        array $tables
    ): void {
        if (empty($tables)) {
            return;
        }
        if (! $this->redisTables) {
            $this->logger->debug('No redis tables, skipping DB updates');
            return;
        }
        $this->redisTables->setTableForDevice($dbTable->tableName, $deviceUuid->toString(), $keyProperties, $tables)
            ->then(function ($result) {
                // $this->logger->notice("Redis said: $result");
            }, function (\Exception $e) {
                $this->logger->error('Setting Redis table failed: ' . $e->getMessage());
            });
    }

    protected function sendResultToRedis(ScenarioResultHandler $handler, object $instance): void
    {
        if (! $this->redisTables) {
            return;
        }
        if ($update = $handler->prepareDbUpdate($instance)) {
            // $this->logger->notice('Sending to Redis: ' . JsonString::encode($update));
            $this->redisTables->setTableEntry(...$update)->then(function ($result) {
                // $this->logger->notice("Redis said: $result");
            }, function (\Exception $e) {
                $this->logger->error('Updating Redis table failed: ' . $e->getMessage());
            });
        }
    }
}
