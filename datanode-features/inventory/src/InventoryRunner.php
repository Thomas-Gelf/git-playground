<?php

namespace IcingaFeature\Inventory;

use Amp\Future;
use gipfl\Json\JsonException;
use gipfl\Json\JsonString;
use IcingaDataNode\Feature;
use IcingaDataNode\Inventory\CentralInventory;
use IcingaDataNode\Inventory\InventoryActionType;
use IcingaDataNode\NodeIdentifier;
use IcingaFeature\Inventory\Db\DbConnection;
use IcingaFeature\Inventory\Db\DbQueryHelper;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Revolt\EventLoop;

use function Amp\async;

class InventoryRunner implements CentralInventory
{
    public function __construct(
        protected readonly Feature $feature,
        protected readonly DbConnection $db,
        protected readonly CredentialLoader $credentials,
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function run(): void
    {
        EventLoop::queue($this->shipLocalSnmpCredentials(...));
        EventLoop::queue($this->shipLocalSnmpCredentials(...));
    }

    public function shipLocalSnmpCredentials(): void
    {
        if ($this->hasLocalSnmpFeatureEnabled()) {
            $this->logger->notice('I HAVE SNMP');
            $localCredentials = CredentialLoader::fetchAllForDataNode($this->feature->nodeIdentifier->uuid, $this->db);
            $rpcHandler->setCredentialsRequest($localCredentials)->catch(function (\Exception $e) {
                $this->logger->error('Sending SNMP credentials failed (InventoryRunner): ' . $e->getMessage());
            });

        } else {
            $this->logger->notice('NO SNMP');
        }
    }

    protected function hasLocalSnmpFeatureEnabled(): bool
    {
        foreach ($this->feature->getRegisteredRpcNamespaces() as $registeredRpcNamespace => $rpcHandler) {
            if ($registeredRpcNamespace === 'snmp' && method_exists($rpcHandler, 'setCredentialsRequest')) {
                return true;
            }
        }

        return false;
    }

    public function setSyncError(UuidInterface $nodeUuid, string $table, \Throwable $e): void
    {
        $db = new DbQueryHelper($this->db->getPool(), $this->logger);
        $db->update($table, [
            'current_error' => $e->getMessage(),
        ], [
            'datanode_uuid' => $nodeUuid->getBytes(),
        ]);
    }

    public function shipBulkActions(array $actions): PromiseInterface
    {
        $deferred = new Deferred();
        if (empty($actions)) {
            EventLoop::queue(function () use ($deferred) {
                $deferred->resolve(null);
            });
            return $deferred->promise();
        }
        $start = microtime(true);
        $transaction = $this->db->transaction();
        $db = new DbQueryHelper($transaction, $this->logger);
        try {
            $queries = [];
            $futures = [];
            foreach ($actions as $action) {
                $futures[] = async(function () use (&$queries, $action, $db) {
                    $table = $action->tableName;
                    $values = $action->getAllDbValues();
                    $keyProperties = $action->getDbKeyProperties();
                    try {
                        $queries[] = match ($action->action) {
                            InventoryActionType::CREATE => $db->insert($table, $values, $this->logger),
                            InventoryActionType::UPDATE => $db->update($table, $action->getDbValuesForUpdate(), $keyProperties),
                            InventoryActionType::DELETE => $db->delete($table, $keyProperties),
                        };
                        $queries[] = $db->insert('datanode_table_action_history', [
                            'datanode_uuid'   => $action->sourceNode->getBytes(),
                            'table_name'      => $table,
                            'stream_position' => $action->streamPosition,
                            'action'          => $action->action->value,
                            'key_properties'  => JsonString::encode($action->keyProperties),
                            'sent_values'     => JsonString::encode($action->values),
                        ]);
                    } catch (JsonException $e) {
                        print_r($action);
                        echo $e->getMessage();
                        echo "\n\n";
                    }
                });
                // TODO: $this->logAction?
            }
            $futures[] = async(function () use ($db, $action) {
                $db->update('datanode_table_sync', [
                    'current_position' => $action->streamPosition,
                    'current_error' => null,
                ], [
                    'datanode_uuid' => $action->sourceNode->getBytes(),
                    'table_name'    => $action->tableName, // Hint: works here, but... not so nice
                ]);
            });
            $this->logger->notice('Waiting for the future');
            Future\awaitAll($futures);
            $this->logger->notice('The future is now');
            $commit = async(function () use ($transaction) {
                $transaction->commit();
            })->catch(function (\Throwable $e) {
                $this->logger->error('COMMIT failed: ' . $e->getMessage());
            })->finally(function () use ($deferred) {
                $deferred->resolve(null);
            });
            Future\await([$commit]);
            $this->logger->notice(sprintf(
                'COMMITTED %d queries in %.02fms',
                count($queries),
                (microtime(true) - $start) * 1000
            ));
            $deferred->resolve([$action->streamPosition]);
        } catch (\Throwable $e) {
            $this->logger->error('Transaction failed ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
            $transaction->rollback();
            try {
                $this->setSyncError($action->sourceNode, $action->tableName, $e);
            } catch (\Throwable $e) {
                $this->logger->notice('Unable to write error information to DB: ' . $e->getMessage());
            }
        }

        return $deferred->promise();
    }

    public function getCredentials(): array
    {
        // TODO: Implement getCredentials() method.
        return [];
    }

    public function loadTableSyncPositions(NodeIdentifier $nodeIdentifier): array
    {
        return $this->db->fetchPairs('SELECT table_name, current_position FROM datanode_table_sync WHERE datanode_uuid = ?', [
            $nodeIdentifier->uuid->getBytes()
        ]);
    }
}
