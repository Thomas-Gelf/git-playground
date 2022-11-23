<?php

namespace IcingaDataNode\Network;

use gipfl\Protocol\JsonRpc\JsonRpcConnection;

interface ConnectionSubscriberInterface
{
    public function activateConnection(string $hexUuid, JsonRpcConnection $connection): void;

    public function deactivateConnection(string $hexUuid): void;
}
