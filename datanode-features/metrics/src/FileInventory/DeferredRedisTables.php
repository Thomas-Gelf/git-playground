<?php

namespace IcingaMetrics\FileInventory;

use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonString;
use gipfl\RrdTool\AsyncRrdtool;
use gipfl\RrdTool\RrdCached\RrdCachedClient;
use gipfl\RrdTool\RrdInfo;
use IcingaDataNode\Monitoring\Ci;
use IcingaDataNode\Monitoring\Measurement;
use IcingaDataNode\Monitoring\Metric;
use IcingaDataNode\NodeIdentifier;
use IcingaDataNode\Redis\RedisTables;
use IcingaMetrics\AsyncDependencies;
use IcingaMetrics\CiConfig;
use IcingaMetrics\RedisPerfDataApi;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use Throwable;

use function count;
use function key;
use function React\Promise\all;
use function React\Promise\resolve;

/**
 * This fetches pending new CIs or CIs with missing DSs in batches, but processes
 * only one at a time to avoid blocking
 */
class DeferredRedisTables
{
    const NS_RRD_DEFINITION = '2e012390-58f9-4e84-8d15-ac61fec61ff1';
    protected RedisTableStore $store;
    protected ?PromiseInterface $checking = null;
    /** @var array<string, Measurement> Key is the JSON-encoded CI definition */
    protected array $pendingCi = [];
    /** @var array<string, Measurement> Key is the JSON-encoded CI definition */
    protected array $pendingDs = [];
    protected UuidInterface $nsRrdDefinition;
    //private string $nodeIdentifier;
    protected ?TimerInterface $timer = null;
    private string $metricStoreIdentifier;

    // TODO
    // Infrastructure needs to be always ready,
    // if one of them fails eventually keep fetching stats
    // from the others, but stop working and get into failed
    // state. Or exit and let the parent process deal with this;
    public function __construct(
        // NodeIdentifier $nodeIdentifier,
        UuidInterface $metricStoreUuid,
        protected readonly RedisPerfDataApi $redisApi,
        protected readonly RedisTables $tables,
        protected readonly RrdCachedClient $rrdCached,
        protected readonly AsyncRrdtool $rrdtool,
        protected readonly LoggerInterface $logger
    ) {
        $this->store = new RedisTableStore($redisApi, $rrdCached, $rrdtool, $logger);
        $this->nsRrdDefinition = Uuid::fromString(self::NS_RRD_DEFINITION);
        // $this->nodeIdentifier = $nodeIdentifier->uuid->toString();
        $this->metricStoreIdentifier = $metricStoreUuid->toString();
    }

    public function run(): void
    {
        try {
            AsyncDependencies::waitFor('DeferredHandler', [
                'Redis'     => $this->redisApi->getRedisConnection(),
                'RrdCached' => $this->rrdCached->stats(),
            ], 5, $this->logger)->then(function () {
                $this->timer = Loop::get()->addPeriodicTimer(1, function () {
                    // We are not checking the return value, as useful logging happens in the method itself
                    $this->checkForDeferred();
                });
            });
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    public function stop(): PromiseInterface
    {
        if ($this->timer) {
            Loop::cancelTimer($this->timer);
            $this->timer = null;
        }

        return resolve(null);
    }

    protected function getRedisConnection() : PromiseInterface
    {
        return $this->redisApi->getRedisConnection();
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
continue;
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
            $this->logger->error('DeferredHandler failed to fetchDeferredCids: ' . $e->getMessage());
        })->finally(function () {
            $this->checking = null;
        });

        return true;
    }

    protected function checkForDeferredX(): bool
    {
        if ($this->checking) {
            $this->logger->notice('Still waiting for deferred items from Redis');
            return false;
        }
        if (! empty($this->pendingCi)) {
            $this->logger->debug(sprintf('There are still %d items pending:', count($this->pendingCi)));
            return false;
        }

        $this->checking = $this->redisApi->fetchDeferredNew()->then(function ($cis) {
            $this->logger->notice('STEP 1111');
            return $this->redisApi->getCiConfigs(array_keys($cis))->then(function ($ciConfigs) use ($cis) {
                $this->logger->notice('STEP 2222');
                return [$ciConfigs, $cis]; // geht nicht
            });
        })->then(function ($cis, $ciConfigs) {
            $this->logger->notice('STEP 33333');
            $this->checking = null;
            if (empty($cis)) {
                return;
            }
            try {
                $this->processDeferredCis($cis);
            } catch (Throwable $e) {
                // TODO: And now??
                $this->logger->error($e->getMessage());
            }
        }, function (Throwable $e) {
            $this->checking = null;
            $this->logger->error('DeferredHandler failed to fetchDeferredCis: ' . $e->getMessage());
        });

        return true;
    }

    protected static function deferredReasonNeedsProcessing(string $reason): bool
    {
        // Unknown DS name "value2"
        return $reason === 'Unknown CI' || str_starts_with($reason, 'Unknown DS name ');
    }

    protected function processDeferredCis(array $cis): void
    {
        $cntDeferred = 0;
        $ignored = [];
        foreach ($cis as $ci => $ciDetails) {
            try {
                $ciDetails = JsonString::decode($ciDetails);
            } catch (JsonDecodeException $exception) {
                $ignored[] = $ci;
                continue;

            }
            if (self::deferredReasonNeedsProcessing($ciDetails->reason)) {
                $this->pendingCi[$ci] = new Measurement(
                    Ci::fromSerialization(JsonString::decode($ci)),
                    (int) $ciDetails->ts,
                    array_map(Metric::fromSerialization(...), $ciDetails->metrics)
                );
                $cntDeferred++;
            }
            // TODO: (else) handle manual deferred, json_decode, -> reason
        }
        if (! empty($ignored)) {
            $this->logger->error('Failed to decode JSON for CI details: ' . self::listSomeNames($ignored));
        }
        $this->checkForPending($cntDeferred);
    }

    protected static function listSomeNames(array $names, int $max = 5): string
    {
        if (count($names) <= $max) {
            return implode(', ', $names);
        }

        return implode(', ', array_slice($names, 0, $max)) . ' and ' . count($names) - $max . ' more';
    }

    protected function checkForPending(int $cntDeferred): void
    {
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
    }

    protected function scheduleDeferredHandler(): void
    {
        // This is useless, we can skip it. WHY?
        Loop::get()->futureTick($this->processDeferredMeasurement(...));
    }

    protected function processDeferredMeasurement(): PromiseInterface
    {
        $ciKey = key($this->pendingCi);
        if ($ciKey === null) {
            return resolve(null);
        }
        $measurement = $this->pendingCi[$ciKey];

        $base = 60; // 60 seconds base for now
        return $this->store->wantCi($measurement, $base)->then(function (RrdInfo $info) use ($measurement, $ciKey) {
            $ci = $measurement->ci;
            $this->logger->debug("DeferredHandler: rescheduling all entries for $ciKey");
            $ns = $this->nsRrdDefinition;
            $rraSetUuidHex = Uuid::uuid5($ns, $info->getRraSet())->toString();
            // TODO: setTableEntries? Batch!
            $promises = [];
            $promises[] = $this->tables->setTableEntry('rrd_archive_set', $rraSetUuidHex, ['uuid'] , [
                'uuid' => $rraSetUuidHex
            ]);
            foreach ($info->getRraSet()->getRras() as $rraIndex => $rra) {
                $promises[] = $this->tables->setTableEntry('rrd_archive', $rraSetUuidHex, ['uuid'] , [
                    'rrd_archive_set_uuid'   => $rraSetUuidHex,
                    'rra_index'              => $rraIndex,
                    'consolidation_function' => $rra->getConsolidationFunction(),
                    'row_count'              => $rra->getRows(),
                    'definition'             => (string) $rra,
                ]);
            }
            $dsListUuidHex = Uuid::uuid5($ns, $info->getDsList())->toString();
            $this->tables->setTableEntry('rrd_datasource_list', $dsListUuidHex, ['uuid'] , [
                'uuid' => $dsListUuidHex
            ]);
            // RrdInfo has applied aliase!!
            foreach ($info->getDsList()->getDataSources() as $dsIndex => $ds) {
                $promises[] = $this->tables->setTableEntry('rrd_datasource', "$dsListUuidHex/$dsIndex", [
                    'datasource_list_uuid',
                    'datasource_index',
                ] , [
                    'datasource_list_uuid' => $dsListUuidHex,
                    'datasource_index'     => $dsIndex,
                    'datasource_name'      => $ds->getAlias(),
                    'datasource_name_rrd'  => $ds->getName(),
                    'datasource_type'      => $ds->getType(),
                    'minimal_heartbeat'    => $ds->getHeartbeat(),
                    'min_value'            => $ds->getMin(),
                    'max_value'            => $ds->getMax(),
                ]);
            }

            // Only for deferred new CI, differs for missing DS
            $fileUuidHex = Uuid::uuid4()->toString();
            $promises[] = $this->tables->setTableEntry('rrd_file', $fileUuidHex, ['uuid'], [
                'uuid'              => $fileUuidHex,
                // 'datanode_uuid'     => $this->nodeIdentifier, // ?!
                'metric_store_uuid' => $this->metricStoreIdentifier,
                'device_uuid'       => $ci->hostname, // ??
                'measurement_name'  => $ci->subject,
                'instance'          => $ci->instance,
                'tags'              => JsonString::encode($ci->tags),
                'filename'          => $info->getFilename(),
                'rrd_step'          => $info->getStep(),
                'rrd_version'       => $info->getRrdVersion(),
                'rrd_header_size'   => $info->getHeaderSize(),
                'rrd_datasource_list_checksum' => $dsListUuidHex,
                'rrd_archive_set_checksum'     => $rraSetUuidHex,
            ]);

            $promises[] = $this->redisApi->rescheduleDeferredCi($ci);

            return all($promises);
        })->finally(function () use ($measurement, $ciKey) {
            if (! isset($this->pendingCi[$ciKey])) {
                $this->logger->error('Finally failed to find CI (BUG!): ' . $ciKey);
            }
            unset($this->pendingCi[$ciKey]);
            $this->scheduleDeferredHandler();
        })->catch(function ($e) {
            $this->logger->error($e->getMessage());
        });
    }

    protected static function getCiLogName(Ci $ci): string
    {
        return JsonString::encode($ci);
    }
}
