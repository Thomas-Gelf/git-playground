<?php

/**
 * This is an Icinga Datanode feature
 *
 * @var Feature $this
 */

use IcingaDataNode\Feature;
use IcingaFeature\Ssh\RpcContextSsh;

require __DIR__ . '/vendor/autoload.php';
$this->registerRpcNamespace('ssh', new RpcContextSsh());
