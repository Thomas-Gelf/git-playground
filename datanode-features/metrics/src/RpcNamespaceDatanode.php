<?php

namespace IcingaMetrics;

use gipfl\DataType\Settings;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use IcingaDataNode\Inventory\RemoteInventory;
use IcingaDataNode\NodeIdentifier;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class RpcNamespaceDatanode
{
    protected NodeIdentifier $identifier;

    public function __construct(
        protected MetricStore $store,
        protected NamespacedPacketHandler $handler,
        protected LoggerInterface $logger
    ) {
        $this->identifier = new NodeIdentifier(
            $this->store->getUuid(),
            $this->store->getNodeUuid()->toString() . '/' . $this->store->getName(),
            gethostbyaddr(gethostbyname(gethostname()))
        );
    }

    public function getIdentifierRequest(): NodeIdentifier
    {
        return $this->identifier;
    }

    public function getSettingsRequest(): Settings
    {
        return $this->store->requireConfig();
    }

    public function getNameRequest(): string
    {
        return $this->identifier->name;
    }

    public function getUuidRequest(): UuidInterface
    {
        return $this->identifier->uuid;
    }

    public function getAvailableMethodsRequest(): array
    {
        try {
            return $this->handler->getKnownMethods();
        } catch (\Throwable $e) {
            return [$e->getMessage()];
        }
    }

    public function getConnectionsRequest(): array
    {
        return [];
    }

    public function getFeaturesRequest(): object
    {
        return (object)[];
    }

    /**
     * @param \gipfl\Protocol\JsonRpc\JsonRpcConnection $connection,
     * @param \IcingaDataNode\RpcDataType\Uuid $datanodeUuid
     * @param array $tablePositions
     * @return bool
     */
    public function setRemoteInventoryRequest(
        JsonRpcConnection $connection,
        UuidInterface $datanodeUuid,
        array $tablePositions
    ): bool {
        $this->logger->notice('Ignoring REMOTE INVENTORY');
        return false;
    }
}
