<?php

namespace IcingaMetrics;

use gipfl\Json\JsonString;
use gipfl\RrdTool\AsyncRrdtool;
use gipfl\RrdTool\RrdCached\RrdCachedClient;
use IcingaDataNode\Monitoring\Ci;
use IcingaDataNode\Monitoring\Measurement;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use Throwable;

use function count;
use function key;

class DeferredHandler
{
    protected Store $store;
    protected RedisPerfDataApi $redisApi;
    protected RrdCachedClient $rrdCached;
    protected LoggerInterface $logger;
    protected ?PromiseInterface $checking = null;
    /** @var array<string, Measurement> Key is the JSON-encoded CI definition */
    protected array $pendingCi = [];
    /** @var array<string, Measurement> Key is the JSON-encoded CI definition */
    protected array $pendingDs = [];

    // TODO
    // Infrastructure needs to be always ready. If one of our dependencies fails,
    // stop working and get into failed state. Or exit and let the parent process deal with this
    public function __construct(
        RedisPerfDataApi $redisApi,
        RrdCachedClient $rrdCached,
        AsyncRrdtool $rrdtool,
        LoggerInterface $logger
    ) {
        $this->redisApi = $redisApi;
        $this->logger = $logger;
        $this->rrdCached = $rrdCached;
        $this->store = new Store($redisApi, $rrdCached, $rrdtool, $logger);
    }

    public function run(): void
    {
        try {
            AsyncDependencies::waitFor('DeferredHandler', [
                'Redis'     => $this->redisApi->getRedisConnection(),
                'RrdCached' => $this->rrdCached->stats(),
            ], 5, $this->logger)->then(function () {
                Loop::get()->addPeriodicTimer(1, function () {
                    if (! $this->checkForDeferred()) {
                        $this->logger->warning('Deferred check/handler is still processing');
                    }
                });
            });
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    protected function checkForDeferred(): bool
    {
        if ($this->checking) {
            $this->logger->notice('Still waiting for deferred items from Redis');
            return false;
        }
        if (! empty($this->pendingCi)) {
            $this->logger->debug(sprintf('There are still %d items pending:', count($this->pendingCi)));
            return false;
        }

        // $this->checking = $this->redisApi->fetchDeferred()->then(function ($cis) {
        $this->checking = $this->redisApi->fetchDeferredNew()->then(function ($missing) {
            $cntMissingCi = 0;
            foreach ($missing['missing-ci'] ?? [] as $ciString => $measurementString) {
                try {
                    $measurement = Measurement::fromSerialization(JsonString::decode($measurementString));
                    $this->pendingCi[$ciString] = $measurement;
                    $cntMissingCi++;
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                }
            }
            $cntMissingDs = 0;
            foreach ($missing['missing-ds'] ?? [] as $ciString => $measurementString) {
                try {
                    $measurement = Measurement::fromSerialization(JsonString::decode($measurementString));
                    $this->pendingDs[$ciString] = $measurement;
                    $cntMissingDs++;
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                }
            }
            if ($cntMissingCi === 0 && $cntMissingDs === 0) {
                return true;
            }
            $cntDeferred = 0;
            $cntPending = count($this->pendingCi);
            if ($cntPending > 0) {
                if ($cntDeferred === $cntPending) {
                    $this->logger->debug(sprintf('%d deferred CIs ready to process', $cntPending));
                } else {
                    $this->logger->debug(sprintf(
                        '%d out of %s deferred CIs ready to process',
                        $cntPending,
                        $cntDeferred
                    ));
                }
                $this->scheduleDeferredHandler();
            }

            return true;
        }, function (Throwable $e) {
            $this->checking = null;
            $this->logger->error('DeferredHandler failed to fetchDeferredCids: ' . $e->getMessage());
        });

        return true;
    }

    protected function scheduleDeferredHandler()
    {
        // This is useless, we can skip it. WHY?
        Loop::get()->futureTick(function () {
            $ci = key($this->pendingCi);
            if ($ci !== null) {
                $this->handleDeferredCi($ci, $this->pendingCi[$ci]);
            }
        });
    }

    protected function handleDeferredCi($ci, Measurement $measurement): PromiseInterface
    {
        $base = 60; // 60 seconds base for now
        return $this->store->wantCi($ci, $measurement, $base)->then(function () use ($ci) {
            $this->logger->debug("DeferredHandler: rescheduling all entries for $ci");
            return $this->redisApi->rescheduleDeferredCi($ci);
        })->then(function () use ($ci) {
            if (empty($this->pendingCi)) {
                $this->logger->debug("DeferredHandler: done with $ci, no more CI pending");
            } else {
                $this->logger->debug("DeferredHandler: done with $ci");
            }
            unset($this->pendingCi[$ci]);
            $this->scheduleDeferredHandler();
        }, function ($e) use ($ci) {
            $this->logger->error($e->getMessage());
            unset($this->pendingCi[$ci]);
            $this->scheduleDeferredHandler();
        });
    }
}
