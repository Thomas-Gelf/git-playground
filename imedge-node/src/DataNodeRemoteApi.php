<?php

namespace IcingaDataNode;

use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use IcingaDataNode\JsonRpc\PacketHandler;
use Psr\Log\LoggerInterface;

class DataNodeRemoteApi extends BaseRemoteApi
{
    protected DataNode $dataNode;
    protected Features $features;
    public PacketHandler $packetHandler;

    public function __construct(LoggerInterface $logger, DataNode $dataNode)
    {
        $this->dataNode = $dataNode;
        $this->packetHandler = new PacketHandler(
            $dataNode->getUuid()->toString(),
            $logger,
            $dataNode->dataNodeConnections
        );
        $dataHandler = new RpcNamespaceDatanode($this->dataNode, $this->packetHandler, $logger);
        $this->packetHandler->registerNamespace('datanode', $dataHandler);
        parent::__construct($logger);
    }

    public function setFeatures(Features $features): void
    {
        $this->features = $features;
        foreach ($features->getLoaded() as $feature) {
            foreach ($feature->getRegisteredRpcNamespaces() as $name => $implementation) {
                $this->packetHandler->registerNamespace($name, $implementation);
            }
        }
    }

    protected function addHandlersToJsonRpcConnection(JsonRpcConnection $connection): void
    {
        $connection->setHandler($this->packetHandler);
    }
}
