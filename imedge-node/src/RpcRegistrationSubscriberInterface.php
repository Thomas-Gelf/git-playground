<?php

namespace IcingaDataNode;

interface RpcRegistrationSubscriberInterface
{
    public function registerRpcNamespace(string $namespace, object $handler): void;
}
