<?php

namespace IcingaMetrics;

use Clue\React\Redis\Client;
use gipfl\Process\ProcessKiller;
use gipfl\Process\ProcessList;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use IcingaDataNode\Feature;
use IcingaDataNode\Logging\LogProxy;
use IcingaDataNode\Monitoring\Measurement;
use IcingaMetrics\Command\ExecCommandString;
use IcingaMetrics\Command\RpcCommand;
use Psr\Log\LoggerInterface;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use RuntimeException;

class FeatureRunner
{
    protected string $binary;
    protected array $loggers = [];
    /** @var MetricStore[] */
    protected array $metricStores = [];
    protected ProcessList $processes;
    protected bool $shuttingDown = false;
    /** @var RedisPerfDataApi[] */
    protected array $redisClients = [];

    public function __construct(
        protected readonly Feature $feature,
        protected readonly LoggerInterface $logger
    ) {
        $this->processes = new ProcessList();
        $this->binary = static::getMetricStoreBinaryFile();
    }

    public function shipMeasurement(Measurement $measurement, string $storeName): void
    {
        if (isset($this->redisClients[$storeName])) {
            $this->redisClients[$storeName]->shipMeasurement($measurement);
        }
    }

    /**
     * @param Measurement[] $measurements
     */
    public function shipMeasurements(array $measurements, string $storeName): void
    {
        if (isset($this->redisClients[$storeName])) {
            $this->redisClients[$storeName]->shipMeasurements($measurements);
        }
    }

    protected function startMetricStore(MetricStore $metricStore): void
    {
        $name = $metricStore->getName();
        $this->loggers[] = $logProxy = new LogProxy($this->logger, "[$name] ");
        $handler = new NamespacedPacketHandler();
        $handler->registerNamespace('logger', $logProxy);
        $cmd = ExecCommandString::create($this->binary, [/* '--directory',*/$metricStore->getBaseDir(), '--debug']);
        $this->logger->notice("Starting Metric store: $name -> " . $cmd);
        $process = new Process($cmd);
        $this->processes->attach($process);
        $rpc = RpcCommand::run($process, $handler, $this->logger);
        $process->stderr->on('data', function ($errStr) {
            $this->logger->error($errStr);
        });
        $pid = $process->getPid();
        $socket = $metricStore->getRedisSocketUri();
        $redis = new RedisPerfDataApi($this->logger, $socket);
        $this->logger->info("Metrics feature connecting to redis ($name) via " . $socket);
        $redis->setClientName(ApplicationFeature::PROCESS_NAME . '::deferred');
        $this->redisClients[$name] = $redis;

        $rpc->on('close', function () use ($metricStore, $process, $pid, $name) {
            $label = "Metric store $name";
            if ($this->shuttingDown) {
                $this->logger->notice("Closed connection to Metric store $label");
            } else {
                ProcessKiller::terminateProcess($process, Loop::get());
                $timeout = 15;
                $this->logger->notice(sprintf(
                    'Closed connection to %s, will restart in %ds',
                    $label,
                    $timeout
                ));
                $this->feature->removeRpcConnection("process:///$pid");
                $this->redisClients[$name]->disconnect();
                unset($this->redisClients[$name]);
                Loop::addTimer($timeout, function () use ($metricStore) {
                    $this->startMetricStore($metricStore);
                });
            }
        });
        /*
        $rpc->request('db.setConnection', [
            $dataNode->requireConfig()->getAsSettings('db')
        ])->then(function () use ($metricStore) {
            $this->logger->notice('Sent DB config to ' . $metricStore->getName());
        }, function (\Exception $e) use ($metricStore) {
            $this->logger->error('Failed sending DB config to ' . $metricStore->getName() . ': ' . $e->getMessage());
        });
        */
        $this->feature->registerRpcConnection($rpc, "process:///$pid");
    }

    protected function subscribeRedis(MetricStore $metricStore)
    {
        $identifier = 'metrics/' . $metricStore->getName();
        $this->feature->services->getRedisClient($identifier)
            ->then(function (Client $client) use ($identifier) {
                $client->subscribe($identifier)->then(function () use ($identifier) {
                    $this->logger->notice("Subscribed to $identifier");
                }, function (\Exception $e) use ($identifier) {
                    $this->logger->error("Failed to subscribe $identifier: " . $e->getMessage());
                });
                $client->on('message', function ($channel, $message) {

                });
            });
    }

    public function run(): void
    {
        $this->initializeMetricStores();
    }

    protected function initializeMetricStores(): void
    {
        foreach ($this->feature->settings->getArray('registered-metric-stores') as $path) {
            try {
                $metrics = new MetricStore($path, $this->logger);
                $metrics->requireBeingConfigured();
                $this->claimMetricStore($metrics);
                $this->startMetricStore($metrics);
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    public function stop(): PromiseInterface
    {
        $this->shuttingDown = true;
        return ProcessKiller::terminateProcesses($this->processes, Loop::get(), 10);
    }

    public function getMetricStores(): array
    {
        return $this->metricStores;
    }

    public function claimMetricStore(MetricStore $store): void
    {
        $store->setNodeUuid($this->feature->nodeIdentifier->uuid);
        $this->metricStores[$store->getUuid()->getBytes()] = $store;
        $path = $store->getBaseDir();
        $registered = $this->feature->settings->getArray('registered-metric-stores');
        if (! in_array($path, $registered)) {
            $registered[] = $path;
            $this->feature->settings->set('registered-metric-stores', $registered);
            $this->feature->storeSettings();
        }
    }

    protected static function getMetricStoreBinaryFile(): string
    {
        $binaryPath = dirname(__DIR__) . '/bin';
        $binaryFile = 'icinga-metricstore';
        $binary = "$binaryPath/$binaryFile";
        if (! file_exists($binary)) {
            throw new RuntimeException("Could not find required executable '$binary' in '$binaryPath'");
        }
        if (! is_executable($binary)) {
            throw new RuntimeException("Cannot execute '$binaryPath/$binary'");
        }

        return $binary;
    }
}
