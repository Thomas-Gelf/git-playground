<?php

namespace IcingaDataNode\Inventory;

use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use IcingaDataNode\NodeIdentifier;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use React\Promise\PromiseInterface;

use function React\Promise\reject;

class RemoteInventory implements CentralInventory
{
    public function __construct(
        protected readonly JsonRpcConnection $connection,
        protected readonly UuidInterface $targetUuid,
        protected readonly array $tableSyncPositions,
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function shipBulkActions(array $actions): PromiseInterface
    {
        try {
            $promise = $this->connection->request('remoteInventory.shipBulkActions', (object) [
                'actions' => $actions
            ])->then(function () use ($actions) {
                $this->logger->notice(count($actions) . ' ACTIONS HAVE been shipped');
            }, function (\Exception $e) use ($actions) {
                $this->logger->notice(sprintf(
                    'Failed to ship %d bulk actions: %s',
                    count($actions),
                    $e->getMessage()
                ));
            });

            return $promise;
        } catch (\Throwable $e) {
            $this->logger->error('WTF: ' . $e->getMessage());
            return reject(new \Exception($e->getMessage()));
        }
    }

    public function getCredentials(): array
    {
        // TODO: Implement getCredentials() method.
        return [];
    }

    public function loadTableSyncPositions(NodeIdentifier $nodeIdentifier): array
    {
        return $this->tableSyncPositions;
    }
}
