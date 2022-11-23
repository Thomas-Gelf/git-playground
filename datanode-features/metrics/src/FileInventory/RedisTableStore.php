<?php

namespace IcingaMetrics\FileInventory;

use gipfl\RrdTool\AsyncRrdtool;
use gipfl\RrdTool\RrdCached\RrdCachedClient;
use gipfl\RrdTool\RrdInfo;
use IcingaDataNode\Monitoring\Ci;
use IcingaDataNode\Monitoring\Measurement;
use IcingaMetrics\CiConfig;
use IcingaMetrics\DsHelper;
use IcingaMetrics\RedisPerfDataApi;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

use function floor;

class RedisTableStore
{
    protected RrdFileStore $rrdFileStore;

    public function __construct(
        protected readonly RedisPerfDataApi $redisApi,
        protected readonly RrdCachedClient $rrdCached,
        protected readonly AsyncRrdtool $rrdTool,
        protected readonly LoggerInterface $logger
    ) {
        $this->rrdFileStore = new RrdFileStore($this->rrdCached, $this->rrdTool, $this->logger);
    }

    /**
     * TODO: I tend to... ?
     *
     * @param $base 1 or 60 -> sec or min
     * @return PromiseInterface<RrdInfo>
     */
    public function wantCi(Measurement $measurement, int $base): PromiseInterface
    {
        $keyValue = [];
        foreach ($measurement->getMetrics() as $key => $metric) {
            $keyValue[$key] = [$metric->type, $metric->value];
        }

        $dsList = DsHelper::getDataSourcesForMeasurement($this->logger, $measurement);
        $ciConfig = CiConfig::forDsList($dsList);

        // Align start to RRD step
        $start = (int) floor($measurement->getTimestamp() / $base) * $base;
        $step = $base === 1 ? 1 : 60;
        return $this->rrdFileStore->createOrTweak($ciConfig->filename, $dsList, $step, $start)
            ->then(function (RrdInfo $info) use ($measurement, $ciConfig, $dsList) {
                return $this->redisApi
                    ->setCiConfigOnly($measurement->ci, $ciConfig)
                    ->then(function () use ($info, $dsList) {
                        $info->getDsList()->applyAliasMapFromDsList($dsList);
                        return $info;
                    });
            });
    }

    public function deferCi(Ci $ci, $filename): PromiseInterface
    {
        return $this->redisApi->deferCi($ci, 'manual')->then(function () use ($filename) {
            return $this->rrdCached->flushAndForget($filename);
        });
    }
}
