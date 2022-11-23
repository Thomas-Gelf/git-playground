<?php

namespace IcingaMetrics;

use gipfl\DataType\Settings;
use gipfl\Process\ProcessList;
use gipfl\RrdTool\AsyncRrdtool;
use gipfl\RrdTool\RrdCached\RrdCachedClient;
use gipfl\SimpleDaemon\DaemonTask;
use IcingaDataNode\JsonRpc\PacketHandler;
use IcingaDataNode\Process\ProcessWithPidInterface;
use IcingaDataNode\Redis\RedisRunner;
use IcingaDataNode\Redis\RedisTables;
use IcingaDataNode\RedisServices;
use IcingaMetrics\Db\ZfDbConnectionFactory;
use IcingaMetrics\FileInventory\DeferredRedisTables;
use IcingaMetrics\Receiver\ReceiverRunner;
use IcingaMetrics\RrdCached\RrdCachedRunner;
use PHPUnit\Util\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Throwable;

use function React\Promise\all;
use function React\Promise\resolve;

class MetricStoreRunner implements DaemonTask, LoggerAwareInterface, ProcessWithPidInterface
{
    use LoggerAwareTrait;

    const DEFAULT_REDIS_BINARY = '/usr/bin/redis-server';
    const DEFAULT_RRD_TOOL_BINARY = '/usr/local/bin/rrdtool';
    const DEFAULT_RRD_CACHED_BINARY = '/usr/local/bin/rrdcached';

    protected ProcessList $processes;
    protected ?RrdCachedRunner $rrdCachedRunner = null;
    protected ?RedisRunner $redisRunner = null;
    protected ?AsyncRrdtool $rrdtool = null;
    protected DeferredRedisTables $deferredHandler;
    protected MainUpdateHandler $mainHandler;
    protected SelfMonitoring $selfMonitoring;

    public function __construct(
        protected readonly MetricStore $metricStore,
        protected readonly PacketHandler $rpcHandler
    ) {
        $this->processes = new ProcessList();
    }

    public function start(LoopInterface $loop): PromiseInterface
    {
        try {
            $this->initialize();
        } catch (Throwable $e) {
            $this->logger->error('Failed to initialize data node: ' . $e->getMessage());
        }

        return resolve(null);
    }

    public function stop(): PromiseInterface
    {
        $this->logger->notice('Stopping metric store ' . $this->metricStore->getName());

        return all([
            $this->redisRunner?->stop(),
            $this->rrdtool?->endProcess(),
            $this->rrdCachedRunner?->stop(),
            $this->deferredHandler?->stop(),
            $this->mainHandler?->stop(),
            $this->selfMonitoring?->stop(),
        ]);
    }

    protected static function getRedisBinary(): string
    {
        return static::DEFAULT_REDIS_BINARY;
    }

    protected static function getRrdCacheDBinary(): string
    {
        return static::DEFAULT_RRD_CACHED_BINARY;
    }

    protected function initialize(): void
    {
        $metricStore = $this->metricStore;
        $metricStore->run();
        $this->rpcHandler->registerNamespace('datanode', new RpcNamespaceDatanode(
            $this->metricStore,
            $this->rpcHandler,
            $this->logger
        ));
        $this->redisRunner()->run();
        $this->rrdCachedRunner = new RrdCachedRunner(
            static::getRrdCacheDBinary(),
            $metricStore->getBaseDir() . '/rrdcached',
            $this->logger
        );
        $this->rrdCachedRunner->run();
        chdir($this->rrdCachedRunner->getDataDir());
        $this->runSelfMonitoring();
        Loop::get()->addTimer(1, function () {
            $this->runDeferredHandler();
            $this->runMainHandler();
        });
        Loop::addTimer(2, function () {
            $this->initializeRrdtool();
        });
        Loop::addTimer(3, function () use ($metricStore) {
            if ($receivers = $metricStore->requireConfig()->get('receivers')) {
                $runner = new ReceiverRunner($this->logger, $receivers, $metricStore);
                $runner->run();
            }
        });
    }

    protected function redisRunner(): RedisRunner
    {
        if ($this->redisRunner === null) {
            $this->redisRunner = new RedisRunner(
                static::getRedisBinary(),
                $this->metricStore->getRedisBaseDir(),
                $this->logger
            );
        }

        return $this->redisRunner;
    }

    protected function initializeRrdtool(): void
    {
        $this->rrdtool = $rrdtool = new AsyncRrdtool(
            $this->rrdCachedRunner->getDataDir(),
            static::getRrdToolBinary(),
            $this->rrdCachedRunner->getSocketFile()
        );
        $rrdtool->setLogger($this->logger);
        $rrdCached = new RrdCachedClient($this->rrdCachedRunner->getSocketFile());
        $rrdHandler = new RpcNamespaceRrd($rrdtool, $rrdCached);
        $this->rpcHandler->registerNamespace('rrd', $rrdHandler);
    }

    protected function onDbConfig(Settings $dbConfig, MetricStore $store): void
    {
        $db = ZfDbConnectionFactory::connection(
            (array) $dbConfig->getAsSettings('db')->jsonSerialize()
        );
        $dbInventory = new DbInventory($db, $this->logger);
        $redis = new RedisPerfDataApi($this->logger, $this->metricStore->getRedisSocketUri());
        $redis->setClientName(ApplicationFeature::PROCESS_NAME . '::ciUpdates');
        $handler = new CiUpdateHandler($dbInventory, $redis, $store->getUuid(), $this->logger);
        $handler->run();
        $store->run();
    }

    protected function runMainHandler(): void
    {
        $socket = $this->metricStore->getRedisSocketUri();
        $redis = new RedisPerfDataApi($this->logger, $socket);
        $this->logger->info('MainHandler connecting to redis via ' . $socket);
        $redis->setClientName(ApplicationFeature::PROCESS_NAME . '::main');
        $rrdCached = new RrdCachedClient($this->rrdCachedRunner->getSocketFile());
        $this->mainHandler = new MainUpdateHandler($redis, $rrdCached, $this->logger);
        $this->mainHandler->run();
    }

    protected function runDeferredHandler(): void
    {
        $socket = $this->metricStore->getRedisSocketUri();
        $redis = new RedisPerfDataApi($this->logger, $socket);
        $this->logger->info('DeferredHandler connecting to redis via ' . $socket);
        $redis->setClientName(ApplicationFeature::PROCESS_NAME . '::deferred');
        $rrdCached = new RrdCachedClient($this->rrdCachedRunner->getSocketFile());
        $rrdCached->setLogger($this->logger);
        $rrdtool = new AsyncRrdtool(
            $this->rrdCachedRunner->getDataDir(),
            static::getRrdToolBinary()
        );
        $rrdtool->setLogger($this->logger);
        // $deferredHandler = new DeferredHandler($redis, $rrdCached, $rrdtool, $this->logger);
        // $services = new RedisServices('/var/lib/icingadatanode/redis/redis.sock', $this->logger);
        $services = new RedisServices(str_replace('redis+unix://', '', $socket), $this->logger);
        $services->getRedisTables(ApplicationFeature::PROCESS_NAME . '::deferred-tables')
            ->then(function (RedisTables $tables) use ($redis, $rrdCached, $rrdtool) {
                $this->deferredHandler = new DeferredRedisTables(
                    $this->metricStore->getNodeUuid(),
                    $redis,
                    $tables,
                    $rrdCached,
                    $rrdtool,
                    $this->logger
                );
                $this->deferredHandler->run();
            }, function (Exception $e) {
                $this->logger->error('No redis tables: ' . $e->getMessage());
            });
    }

    protected function runSelfMonitoring(): void
    {
        $redis = new RedisPerfDataApi($this->logger, $this->metricStore->getRedisSocketUri());
        $redis->setClientName(ApplicationFeature::PROCESS_NAME . '::self-monitoring');
        $rrdCached = new RrdCachedClient($this->rrdCachedRunner->getSocketFile());
        $rrdCached->setLogger($this->logger);
        $monitor = new SelfMonitoring($redis, $rrdCached, $this->logger, $this->metricStore->getUuid()->toString());
        $monitor->watchProcessRunners([
            'redis-server' => $this->redisRunner,
            'rrdcached'    => $this->rrdCachedRunner,
            'metric-store' => $this,
        ]);
        // $monitor->on(RedisPerfDataApi::ON_PERF_DATA, [$redis, 'shipPerfData']);
        $monitor->on(RedisPerfDataApi::ON_MEASUREMENTS, function ($measurements) use ($redis) {
            try {
                $redis->shipMeasurements($measurements)->then(function ($result) {
                    $pairs = [];
                    foreach ((array) $result as $k => $v) {
                        if ($v > 0) {
                            $pairs[] = "$k = $v";
                        }
                    }
                    if (! empty($pairs)) {
                        $this->logger->notice(implode(', ', $pairs));
                    }
                })->catch(function (Exception $e) {
                    $this->logger->error('Shipping measurements failed: ' . $e->getMessage());
                });
            }  catch (Throwable $e) {
                $this->logger->error($e->getMessage());
            }
        });
        $monitor->run(15);
        $this->selfMonitoring = $monitor;
    }

    public function getProcessPid(): ?int
    {
        return getmypid();
    }

    protected static function getRrdToolBinary(): string
    {
        return static::DEFAULT_RRD_TOOL_BINARY;
    }
}
