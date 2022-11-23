<?php

namespace IcingaDataNode;

use Clue\React\Redis\Client;
use gipfl\DataType\Settings;
use gipfl\Json\JsonString;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use IcingaDataNode\Inventory\RemoteInventory;
use IcingaDataNode\JsonRpc\PacketHandler;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use Revolt\EventLoop;

/**
 * Provides datanode.* JSON-RPC methods
 *
 * All methods with names ending in `Request` or `Notification` are exposed. For
 * now, phpdoc typehints are necessary where a specific parameter type is required.
 * Custom class parameters must implement the JsonSerializable interface
 *
 * TODO, listAvailableFeatures, enable/configureFeature, -> prefixed namespace
 */
class RpcNamespaceDatanode
{
    public function __construct(
        protected DataNode $node,
        protected PacketHandler $handler,
        protected LoggerInterface $logger
    ) {}

    public function getSettingsRequest(): Settings
    {
        return $this->node->requireConfig();
    }

    public function getIdentifierRequest(): NodeIdentifier
    {
        return $this->node->identifier;
    }

    public function getNameRequest(): string
    {
        return $this->node->getName();
    }

    public function getUuidRequest(): string
    {
        return $this->node->getUuid()->toString();
    }

    public function getAvailableMethodsRequest(): array
    {
        return $this->handler->getKnownMethods();
    }

    /**
     * @param string $peerAddress
     * @param bool $persist
     */
    public function connectRequest(string $peerAddress, bool $persist): bool
    {
        if ($persist) {
            $this->logger->notice('Pers req');
            $config = $this->node->requireConfig();
            $connections = $config->getArray(DataNode::CONFIG_PERSISTED_CONNECTIONS);
            if (! isset($connections[$peerAddress])) {
                $connections[$peerAddress] = (object) [];
                $config->set(DataNode::CONFIG_PERSISTED_CONNECTIONS, $connections);
                $this->node->storeConfig($config);
            }
        }
        $this->node->connectionHandler->connect($peerAddress);

        return true;
    }

    /**
     * @param string $peerAddress
     * @param bool $persist
     */
    public function disconnectRequest(string $peerAddress, bool $persist): bool
    {
        if ($persist) {
            $config = $this->node->requireConfig();
            $connections = $config->getArray(DataNode::CONFIG_PERSISTED_CONNECTIONS);
            if (isset($connections[$peerAddress])) {
                unset($connections[$peerAddress]);
                $this->node->storeConfig($config);
                $config->set(DataNode::CONFIG_PERSISTED_CONNECTIONS, $connections);
                $this->node->storeConfig($config);
            }
        }
        $this->node->connectionHandler->disconnect($peerAddress);

        return true;
    }

    public function getConnectionsRequest(): array
    {
        return $this->node->connectionHandler->getConnections();
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
        $this->logger->notice('GOT REMOTE INVENTORY');
        $this->node->setCentralInventory(new RemoteInventory(
            $connection,
            $datanodeUuid,
            $tablePositions,
            $this->logger
        ));

        return true;
    }
/*
    public function getDataNodeConnectionsRequest(): array
    {
        return $this->node->dataNodeConnections->getConnections();
    }

    public function getEstablishedConnectionsRequest(): object
    {
        $result = [];
        foreach ($this->node->establishedConnections as $name => $connection) {
            $result[$name] = (object) [
                'name' => $name,
                'destination' => $connection->destination,
            ];
        }

        return (object) $result;
    }
*/

    public function restartRequest(): bool
    {
        // Grant some time to ship the response
        EventLoop::delay(0.1, function () {
            $this->node->restart();
        });

        return true;
    }

    public function getFeaturesRequest(): object
    {
        $features = [];
        foreach ($this->node->getFeatures()->getLoaded() as $loaded) {
            $features[$loaded->name] = (object) [
                'name'       => $loaded->name,
                'directory'  => $loaded->directory,
                'registered' => $loaded->isRegistered(),
                'enabled'    => true,
            ];
        }

        return (object) $features;
    }

    /**
     * @param string $name
     * @param string $sourcePath
     * @return bool
     */
    public function enableFeatureRequest(string $name, string $sourcePath): bool
    {
        $features = $this->node->getFeatures();
        $features->enable($name, $sourcePath);
        $features->load($name, $sourcePath);
        return true;
    }

    /**
     * Unused. kill?
     *
     * @param ConnectionInterface $peer
     * @param string $topic
     * @return PromiseInterface
     */
    public function subscribeRequest(JsonRpcConnection $peer, string $topic, string $requestName): PromiseInterface
    {
        $this->redis->subscribe($topic)->then(static function (Client $client) use ($peer, $requestName) {
            $client->on('message', function ($topic, $message) use ($peer, $requestName) {
                $peer->notification($requestName, JsonString::decode($message));
            });
        });
    }
}
