<?php

namespace IcingaDataNode;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Exception;
use gipfl\CertificateStore\TrustStore\TrustStoreDirectory;
use gipfl\Protocol\JsonRpc\Error;
use gipfl\Protocol\JsonRpc\Handler\FailingPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\NetString\StreamWrapper;
use gipfl\Socket\UnixSocketInspection;
use gipfl\Socket\UnixSocketPeer;
use Psr\Log\LoggerInterface;
use React\Socket\ConnectionInterface;
use Revolt\EventLoop;

use function posix_getegid;

abstract class BaseRemoteApi implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected ControlSocket $controlSocket;
    protected NetworkSocket $networkSocket;

    public function __construct(
        protected LoggerInterface $logger
    ) {
    }

    public function run(string $socketPath): void
    {
        $this->initializeControlSocket($socketPath);
        // $this->initializeNetworkSocket();
    }

    protected function initializeControlSocket(string $path): void
    {
        if (empty($path)) {
            throw new \InvalidArgumentException('Control socket path expected, got none');
        }
        $this->logger->info("[socket] launching control socket in $path");
        $socket = new ControlSocket($path);
        $socket->run();
        $this->addSocketEventHandlers($socket);
        $this->controlSocket = $socket;
    }

    protected function initializeNetworkSocket(string $address = '0.0.0.0', int $port = 5670): void
    {
        $this->logger->info("[socket] launching network socket at $address:$port");
        // $trustStore = new TrustStoreDirectory($this->)
        $socket = new NetworkSocket($address, $port);
        $socket->run();
        $this->addSocketEventHandlers($socket);
        $this->networkSocket = $socket;
    }

    protected function isAllowed(UnixSocketPeer $peer): bool
    {
        if ($peer->getUid() === 0) {
            return true;
        }
        $myGid = posix_getegid();
        $peerGid = $peer->getGid();
        // Hint: $myGid makes also part of id -G, this is the fast lane for those using
        //       php-fpm and the user icingaweb2 (with the very same main group as we have)
        if ($peerGid === $myGid) {
            return true;
        }

        $uid = $peer->getUid();
        return in_array($myGid, array_map(intval(...), explode(' ', `id -G $uid`)));
    }

    abstract protected function addHandlersToJsonRpcConnection(JsonRpcConnection $connection);

    protected function addSocketEventHandlers(EventEmitterInterface $socket): void
    {
        $socket->on('connection', function (ConnectionInterface $connection) {
            $jsonRpc = new JsonRpcConnection(new StreamWrapper($connection));
            $jsonRpc->setLogger($this->logger);

            $peer = UnixSocketInspection::getPeer($connection);
            if (!$this->isAllowed($peer)) {
                $jsonRpc->setHandler(new FailingPacketHandler(new Error(Error::METHOD_NOT_FOUND, sprintf(
                    '%s is not allowed to control this socket',
                    $peer->getUsername()
                ))));
                EventLoop::delay(10, function () use ($connection) {
                    $connection->close();
                });
                return;
            }

            $this->addHandlersToJsonRpcConnection($jsonRpc);
        });
        $socket->on('error', function (Exception $error) {
            // Connection error, Socket remains functional
            $this->logger->error($error->getMessage());
        });
    }
}
