<?php

namespace IcingaDataNode\Command;

use gipfl\Protocol\JsonRpc\JsonRpcConnection;

interface RpcCommand
{
    public function rpc(): JsonRpcConnection;
}
