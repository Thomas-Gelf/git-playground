<?php

namespace IcingaDataNode\JsonRpc;

use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\NetString\StreamWrapper;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\UnixConnector;
use RuntimeException;

use function React\Promise\resolve;

class RemoteClient
{
    public ?JsonRpcConnection $connection = null;
    protected ?PromiseInterface $pendingConnection = null;

    public function __construct(
        public string $destination
    ) {
    }

    public function request($method, $params = null): PromiseInterface
    {
        return $this->connection()->then(function (JsonRpcConnection $connection) use ($method, $params) {
            return $connection->request($method, $params);
        });
    }

    public function notify($method, $params = null): PromiseInterface
    {
        return $this->connection()->then(function (JsonRpcConnection $connection) use ($method, $params) {
            $connection->notification($method, $params);
        });
    }

    protected function connection(): PromiseInterface
    {
        if ($this->connection === null) {
            if ($this->pendingConnection === null) {
                return $this->connect();
            }

            return $this->pendingConnection;
        }

        return resolve($this->connection);
    }

    public function connect(): PromiseInterface
    {
        if (str_starts_with($this->destination, 'unix://')) {
            $connector = new UnixConnector();
        } else {
            throw new RuntimeException('RPC destination not supported: ' . $this->destination);
        }

        return $this->pendingConnection = $connector
            ->connect($this->destination)
            ->then($this->onConnect(...));
    }

    protected function onConnect(ConnectionInterface $connection): JsonRpcConnection
    {
        $jsonRpc = new JsonRpcConnection(new StreamWrapper($connection));
        $this->connection = $jsonRpc;
        $this->pendingConnection = null;
        $connection->on('close', function () {
            $this->connection = null;
        });

        return $jsonRpc;
    }
}
