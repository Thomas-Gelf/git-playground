<?php

namespace IcingaDataNode\Command;

use GetOpt\Command;
use GetOpt\GetOpt;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\NetString\StreamWrapper;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

class DbCommand extends Command implements RpcCommand, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected ?JsonRpcConnection $rpc = null;
    protected ?NamespacedPacketHandler $packetHandler = null;

    public function __construct()
    {
        parent::__construct('db', [$this, 'handle']);
        $this->setDescription(sprintf('Internal, provides DB connections'));
    }

    public function handle(GetOpt $options): void
    {
        $handler = $this->packetHandler();
        $handler->registerNamespace('db', new DbRunner($this->logger));

        Loop::run();
    }

    protected function packetHandler(): NamespacedPacketHandler
    {
        if ($this->packetHandler === null) {
            $this->packetHandler = new NamespacedPacketHandler();
        }

        return $this->packetHandler;
    }

    public function rpc(): JsonRpcConnection
    {
        if ($this->rpc === null) {
            $this->rpc = new JsonRpcConnection(new StreamWrapper(
                new ReadableResourceStream(STDIN),
                new WritableResourceStream(STDOUT)
            ), $this->packetHandler());
        }

        return $this->rpc;
    }
}
