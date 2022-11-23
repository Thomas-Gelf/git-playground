<?php

namespace IcingaDataNode\JsonRpc;

use gipfl\OpenRpc\Reflection\MetaDataMethod;
use gipfl\Protocol\JsonRpc\Error;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\Request;
use IcingaDataNode\Network\DataNodeConnections;
use Psr\Log\LoggerInterface;

class PacketHandler extends NamespacedPacketHandler
{
    const NO_SUCH_TARGET = -32004;

    /** @var array<string, JsonRpcConnection> */
    protected array $targets = [];

    public function __construct(
        public string $identifier,
        public LoggerInterface $logger,
        public readonly ?DataNodeConnections $dataNodeConnections = null,
    ) {}

    /**
     * @return array<string, MetaDataMethod>
     */
    public function getKnownMethods(): array
    {
        return $this->knownMethods;
    }

    public function processNotification(Notification $notification, JsonRpcConnection $connection): void
    {
        $this->logger->debug($notification->toString());

        if ($this->dataNodeConnections && $target = $notification->getExtraProperty('target')) {
            if ($target === $this->identifier) {
                parent::processNotification($notification, $connection);
                return;
            }
            $this->dataNodeConnections->getOptionalConnection($target)->sendNotification($notification);
        } else {
            parent::processNotification($notification, $connection);
        }
    }

    public function processRequest(Request $request, JsonRpcConnection $connection)
    {
        $this->logger->debug($request->toString());

        if ($this->dataNodeConnections && $target = $request->getExtraProperty('target')) {
            if ($target === $this->identifier) {
                return parent::processRequest($request, $connection);
            }
            if ($rpc = $this->dataNodeConnections->getOptionalConnection($target)) {
                return $rpc->sendRequest($request);
            } else {
                if ($this->dataNodeConnections->hasConnections()) {
                    return new Error(self::NO_SUCH_TARGET, sprintf(
                        'I am not %s. Connections: %s',
                        $target,
                        implode(', ', $this->dataNodeConnections->listActiveUuids())
                    ));
                }

                return new Error(self::NO_SUCH_TARGET, sprintf(
                    'I am not %s and not connected to other nodes',
                    $target
                ));
            }
        } else {
            return parent::processRequest($request, $connection);
        }
    }
}
