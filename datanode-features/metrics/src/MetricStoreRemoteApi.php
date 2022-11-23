<?php

namespace IcingaMetrics;

use Exception;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\NetString\StreamWrapper;
use gipfl\RrdTool\AsyncRrdtool;
use gipfl\RrdTool\RrdCached\RrdCachedClient;
use Psr\Log\LoggerInterface;
use React\Socket\ConnectionInterface;

class MetricStoreRemoteApi
{
    protected AsyncRrdtool $rrdtool;
    protected RrdCachedClient $rrdCached;
    protected LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        AsyncRrdtool $rrdtool,
        RrdCachedClient $rrdCached
    ) {
        $this->rrdtool = $rrdtool;
        $this->rrdCached = $rrdCached;
        $this->logger = $logger;
    }

    protected function addSocketEventHandlers(ControlSocket $socket)
    {
        $socket->on('connection', function (ConnectionInterface $connection) {
            $handler = new NamespacedPacketHandler();
            $this->addHandlers($handler);

            $jsonRpc = new JsonRpcConnection(new StreamWrapper($connection), $handler);
            $jsonRpc->setLogger($this->logger);
        });
        $socket->on('error', function (Exception $error) {
            // Connection error, Socket remains functional
            $this->logger->error($error->getMessage());
        });
    }

    protected function addHandlers(NamespacedPacketHandler $handler)
    {
        $rrdHandler = new RpcNamespaceRrd($this->rrdtool, $this->rrdCached);
        $handler->registerNamespace('rrd', $rrdHandler);
    }
}
