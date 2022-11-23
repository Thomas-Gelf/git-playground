<?php

/** @var \IcingaDataNode\Feature $this */

use IcingaFeature\Tcp\RpcContextTcp;

require __DIR__ . '/vendor/autoload.php';
$this->registerRpcNamespace('tcp', new RpcContextTcp());
