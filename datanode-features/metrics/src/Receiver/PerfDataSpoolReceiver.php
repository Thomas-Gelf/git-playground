<?php

namespace IcingaMetrics\Receiver;

use IcingaMetrics\ApplicationFeature;
use IcingaMetrics\PerfDataShipper;
use IcingaMetrics\RedisPerfDataApi;

class PerfDataSpoolReceiver extends BaseReceiver
{
    public function run(): void
    {
        $redis = new RedisPerfDataApi($this->logger, $this->metricStore->getRedisSocketUri());
        $redis->setClientName(ApplicationFeature::PROCESS_NAME . '::perfdataShipper');
        $perf = new PerfDataShipper($this->logger, $this->settings->getRequired('spool-directory'));
        $redis->on(RedisPerfDataApi::ON_STRAIN_START, function ($count) use ($perf) {
            $this->logger->notice(sprintf('%d items waiting for Redis, pause reading', $count));
            $perf->pause();
        });
        $redis->on(RedisPerfDataApi::ON_STRAIN_END, function ($count) use ($perf) {
            $this->logger->notice(sprintf('%d items waiting for Redis, resume reading', $count));
            $perf->resume();
        });
        $perf->on(PerfDataShipper::ON_MEASUREMENT, $redis->shipMeasurement(...));
        $perf->on(PerfDataShipper::ON_MEASUREMENTS, $redis->shipMeasurements(...));
        $perf->run();
    }
}
