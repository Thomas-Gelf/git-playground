<?php

/**
 * This is an Icinga Datanode feature
 *
 * @var Feature $this
 */

use IcingaDataNode\Feature;
use IcingaMetrics\FeatureRunner;
use IcingaMetrics\RpcNamespaceMetrics;
use Revolt\EventLoop;

require __DIR__ . '/vendor/autoload.php';

$runner = new FeatureRunner($this, $this->logger);
$this->registerRpcNamespace('metrics', new RpcNamespaceMetrics($runner, $this->logger));
$this->onShutdown($runner->stop(...));
$this->events->on('measurements', function ($measurements) use ($runner) {
    // TODO: ?!?!?
    $storeName = 'snmp';
    $this->logger->notice('Got measurement: ' . count($measurements));
    try {
        $runner->shipMeasurements($measurements, $storeName);
        $this->logger->notice('Shipped? ' . count($measurements));
    } catch (Throwable $e) {
        $this->logger->error('Not shipped: ' . $e->getMessage());
    }
});

EventLoop::queue($runner->run(...));
