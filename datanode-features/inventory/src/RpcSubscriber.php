<?php

namespace IcingaFeature\Inventory;

use IcingaDataNode\NodeIdentifier;
use IcingaDataNode\RpcRegistrationSubscriberInterface;
use IcingaFeature\Snmp\SnmpCredentials;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;

class RpcSubscriber implements RpcRegistrationSubscriberInterface
{
    public function __construct(
        protected NodeIdentifier $nodeIdentifier,
        protected LoggerInterface $logger,
        protected CredentialLoader $credentialLoader,
        // protected
    ) {}

    public function registerRpcNamespace(string $namespace, object $handler): void
    {
        if ($namespace === 'snmp') {
            $this->logger->notice('Inventory RPC subscriber got SNMP feature');
            EventLoop::queue(function () use ($handler) {
                $this->shipLocalSnmpCredentials($handler);
            });
        }
    }

    protected function shipLocalSnmpCredentials($handler): void
    {
        $credentials = $this->credentialLoader->fetchAllForDataNode($this->nodeIdentifier->uuid);
        foreach ($credentials as &$row) {
            $row = SnmpCredential::fromDbRow($row)->jsonSerialize();
        }
        $handler->setCredentialsRequest(SnmpCredentials::fromSerialization($credentials));
        $this->logger->notice('Local SNMP credentials done');
    }
}
