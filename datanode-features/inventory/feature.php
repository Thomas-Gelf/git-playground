<?php

use IcingaDataNode\Feature;
use IcingaFeature\Inventory\ConnectionSubscriber;
use IcingaFeature\Inventory\CredentialLoader;
use IcingaFeature\Inventory\Db\DbConnection;
use IcingaFeature\Inventory\InventoryRunner;
use IcingaFeature\Inventory\RpcContextInventory;
use IcingaFeature\Inventory\RpcSubscriber;
use Revolt\EventLoop;

require __DIR__ . '/vendor/autoload.php';
/** @var Feature $this */
$db = new DbConnection();
$credentials = new CredentialLoader($db);
$runner = new InventoryRunner($this, $db, $credentials, $this->logger);
EventLoop::queue($runner->run(...)); // Order matters
$this->registerRpcNamespace('inventory', new RpcContextInventory($runner));
$this->subscribeRpcRegistrations(new RpcSubscriber($this->nodeIdentifier, $this->logger, $credentials));
$this->subscribeConnections(new ConnectionSubscriber($runner, $this->nodeIdentifier, $this->logger, $credentials));
$this->registerInventory($runner);
