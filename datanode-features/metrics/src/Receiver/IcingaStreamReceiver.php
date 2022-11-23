<?php

namespace IcingaMetrics\Receiver;

use IcingaMetrics\ApplicationFeature;
use IcingaMetrics\IcingaStreamer;
use IcingaMetrics\RedisPerfDataApi;

/**
 * UNUSED
 */
class IcingaStreamReceiver extends BaseReceiver
{
    public function run(): void
    {
        /*
        $redis = new RedisPerfDataApi($this->logger, $this->metricStore->getRedisSocketUri());
        $redis->setClientName(ApplicationFeature::PROCESS_NAME . '::icinga-stream');
        $icinga = new IcingaStreamer($this->logger, $this->settings);
        $icinga->on($redis::ON_PERF_DATA, $redis->shipPerfData(...));
        */
    }
}
