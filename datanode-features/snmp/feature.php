<?php

/**
 * This is an Icinga Datanode feature
 *
 * @var Feature $this
 */

use IcingaDataNode\Feature;
use IcingaFeature\Snmp\RpcContextSnmp;
use IcingaFeature\Snmp\SnmpRunner;
use Revolt\EventLoop;

require __DIR__ . '/vendor/autoload.php';

$runner = new SnmpRunner($this->nodeIdentifier, $this->logger, $this->events, $this->services);
EventLoop::queue($runner->run(...));
$rpcContext = new RpcContextSnmp($runner, $this->logger);
$this->registerRpcNamespace('snmp', $rpcContext);

$this->onShutdown($rpcContext->shutdown(...));
$this->onShutdown($runner->stop(...));
