<?php

namespace IcingaMetrics;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use gipfl\LinuxHealth\Cpu;
use gipfl\LinuxHealth\Memory;
use gipfl\LinuxHealth\Network;
use gipfl\RrdTool\RrdCached\RrdCachedClient;
use IcingaDataNode\Monitoring\Ci;
use IcingaDataNode\Monitoring\Measurement;
use IcingaDataNode\Monitoring\Metric;
use IcingaDataNode\Monitoring\MetricDatatype;
use IcingaDataNode\Process\ProcessWithPidInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class SelfMonitoring implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected RedisPerfDataApi $redisApi;
    protected RrdCachedClient $rrdCached;
    protected LoggerInterface $logger;
    protected ?PromiseInterface $fetchingRedis = null;
    protected string $ciHostName;
    protected ?TimerInterface $timer = null;
    /** @var ProcessWithPidInterfaceOld[] */
    protected array $processRunners;

    public function __construct(
        RedisPerfDataApi $redisApi,
        RrdCachedClient $rrdCached,
        LoggerInterface $logger,
        string $ciName
    ) {
        $this->redisApi = $redisApi;
        $this->rrdCached = $rrdCached;
        $this->logger = $logger;
        $this->ciHostName = $ciName;
    }

    public function watchProcessRunners(array $runners): void
    {
        foreach ($runners as $name => $runner) {
            $this->setProcessRunner($name, $runner);
        }
    }

    public function setProcessRunner($name, ProcessWithPidInterface $runner): void
    {
        $this->processRunners[$name] = $runner;
    }

    public function run($interval): void
    {
        $this->timer = Loop::get()->addPeriodicTimer($interval, function () {
            $this->emitRedisCounters();
            $this->emitRrdCachedCounters();
            $this->emitCpuPerformance();
            $this->emitInterfaceCounters();
            $this->emitRedisProcessCounters();
        });

        $this->logger->info("SelfHealthChecker is ready to start");
    }

    public function stop(): PromiseInterface
    {
        if ($this->timer) {
            Loop::cancelTimer($this->timer);
            $this->timer = null;
        }

        return resolve(null);
    }

    protected function emitRedisCounters(): void
    {
        if ($this->fetchingRedis) {
            $this->logger->notice('Redis counters are overdue');
            return;
        }
        $this->fetchingRedis = $this->redisApi->getCounters()->then(function ($counters = null) {
            $this->fetchingRedis = null;
            /*
            $this->emit(RedisPerfDataApi::ON_PERF_DATA, [
                new PerfData(new Ci($this->ciName, 'RRDHealth'), self::makeCounters((array) $counters), time())
            ]);
            */
            $this->emitMeasurements([
                new Measurement(new Ci($this->ciHostName, 'RRDHealth'), time(), self::makeCounterMetrics((array) $counters))
            ]);
        }, function (\Throwable $e) {
            $this->fetchingRedis = null;
            $this->logger->error($e->getMessage());
        });
    }

    protected function emitRrdCachedCounters(): void
    {
        $this->rrdCached
            ->stats()
            ->then(function ($result) {
                $metrics = [];
                foreach ($result as $k => $v) {
                    if (in_array($k, ['QueueLength', 'TreeNodesNumber', 'TreeDepth'])) {
                        $metrics[] = new Metric($k, $v);
                    } else {
                        $metrics[] = new Metric($k, $v, MetricDatatype::COUNTER);
                    }
                }
                /*
                foreach ($result as $key => & $value) {
                    if (! in_array($key, ['QueueLength', 'TreeNodesNumber', 'TreeDepth'])) {
                        $value .= 'c';
                    }
                }
                unset($value);
                $this->emit(RedisPerfDataApi::ON_PERF_DATA, [
                    new PerfData(new Ci($this->ciName, 'RRDCacheD'), $result, time())
                ]);
                */
                $this->emitMeasurements([
                    new Measurement(
                        new Ci($this->ciHostName, 'RRDCacheD'),
                        time(),
                        $metrics
                    )
                ]);
            }, function (\Exception $e) {
                $this->logger->error('SelfHealthCheck got no data from RRDCacheD: ' . $e->getMessage());
            });
    }

    protected function emitInterfaceCounters(): void
    {
        $measurements = [];
        foreach (Network::getInterfaceCounters() as $ifName => $counters) {
            /*
            $this->emit(RedisPerfDataApi::ON_PERF_DATA, [
                new PerfData(
                    new Ci($this->ciName, 'Interface', $ifName),
                    self::makeCounters((array) $counters),
                    time()
                )
            ]);
            */
            $measurements[] = new Measurement(
                    new Ci($this->ciHostName, 'Interface', $ifName),
                    time(),
                    self::makeCounterMetrics((array) $counters),
            );
        }
        $this->emitMeasurements($measurements);
    }

    protected function emitCpuPerformance(): void
    {
        $counters = Cpu::getCounters();
        $measurements = [];
        foreach ($counters as $cpu => $cpuCounters) {
            /*
            $this->emit(RedisPerfDataApi::ON_PERF_DATA, [
                new PerfData(new Ci($this->ciName, 'CPU', $cpu), self::makeCounters($cpuCounters), time())
            ]);
            */
            $measurements[] = new Measurement(
                new Ci($this->ciHostName, 'CPU', $cpu),
                time(),
                self::makeCounterMetrics($cpuCounters)
            );
        }
        $this->emitMeasurements($measurements);
    }

    protected function emitRedisProcessCounters(): void
    {
        $measurements = [];
        foreach ($this->processRunners as $name => $runner) {
            if ($pid = $runner->getProcessPid()) {
                $memory = Memory::getUsageForPid($pid);
            } else {
                $memory = (object) [
                    'size'   => null,
                    'rss'    => null,
                    'shared' => null,
                ];
            }
            $measurements[] = new Measurement(
                new Ci($this->ciHostName, 'Memory', $name),
                time(),
                [
                    new Metric('size', $memory->size),
                    new Metric('rss', $memory->rss),
                    new Metric('shared', $memory->shared),
                ]
            );
            /*
            $this->emit(RedisPerfDataApi::ON_PERF_DATA, [
                new PerfData(new Ci($this->ciName, 'Memory', $name), (array) $memory, time())
            ]);
            */
        }
        $this->emitMeasurements($measurements);
    }

    protected function emitMeasurements(array $measurements): void
    {
        if (empty($measurements)) {
            return;
        }

        $this->emit(RedisPerfDataApi::ON_MEASUREMENTS, [$measurements]);

    }

    protected static function makeCounters(array $counters): array
    {
        $result = [];
        foreach ($counters as $key => $value) {
            $result[$key] = $value . 'c';
        }

        return $result;
    }

    protected static function makeCounterMetrics(array $counters): array
    {
        $result = [];
        foreach ($counters as $key => $value) {
            $result[] = new Metric($key, $value, MetricDatatype::COUNTER);
        }

        return $result;
    }

    public function __destruct()
    {
        if ($this->timer) {
            Loop::cancelTimer($this->timer);
            $this->timer = null;
        }
    }
}
