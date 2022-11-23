<?php

namespace IcingaDataNode\Command;

use GetOpt\Command;
use GetOpt\GetOpt;
use gipfl\Json\JsonString;
use IcingaDataNode\Application;
use IcingaDataNode\DataNode;
use IcingaDataNode\Inventory\InventoryAction;
use IcingaDataNode\Inventory\InventoryActionType;
use IcingaFeature\Inventory\Db\DbConnection;
use IcingaFeature\Inventory\Db\DbQueryHelper;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Ramsey\Uuid\Uuid;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Revolt\EventLoop;

use function Amp\async;

class LabCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected DbConnection $db;

    public function __construct()
    {
        parent::__construct('lab', [$this, 'handle']);
        $this->setDescription(sprintf(
            'Lab for %s',
            Application::PROCESS_NAME
        ));

        /*
        $this->addOptions([
            Option::create(null, 'to', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Connect to, e.g. --to 192.0.2.100:5669'),
            Option::create(null, 'persist')
                ->setDescription('Persist connection, will be re-established at every start'),
        ]);
        */
    }

    public function handle(GetOpt $options): void
    {
        $home ??= $_ENV['HOME'] ?? $_SERVER['HOME'];
        if ($home === null) {
            echo "Got no --directory and could not detect \$HOME\n";
            exit(1);
        }

        $logger = ProcessLogger::createForOptions($options);
        ProcessLogger::detectAndApplyLogWriter($logger, Application::LOG_NAME, $options);
        Application::checkRequirements($logger);

        $runner = new DataNode($home, $logger);
        EventLoop::queue(function () use ($runner) {
            // $runner->stop();
        });
        $runner->start();
        $db = new DbConnection();
        $this->db = $db;
        $result = $db->fetchAll(
            'SELECT * FROM datanode_table_action_history WHERE datanode_uuid = ?'
            . ' AND action = ? ORDER BY stream_position ASC LIMIT 1000', [
            hex2bin('37333033343565382D353539622D3435'),
            'update'
        ]);
        $actions = [];
        foreach ($result as $row) {
            $keyDings = '';
            $actions[] = new InventoryAction(
                Uuid::fromBytes($row['datanode_uuid']),
                $row['table_name'],
                $row['stream_position'],
                InventoryActionType::from($row['action']),
                $keyDings,
                sha1($keyDings),
                JsonString::decode($row['key_properties']),
                (array) JsonString::decode($row['sent_values'])
            );
        }
        print_r($actions);
        echo microtime(true) . ": Got em\n";
        $this->shipBulkActions($actions);
        echo microtime(true) . ": Wrote em\n";
    }

    public function timerTest()
    {

    }


    /**
     * @param InventoryAction[] $actions
     * @return PromiseInterface
     */
    public function shipBulkActions(array $actions): PromiseInterface
    {
        $deferred = new Deferred();
        if (empty($actions)) {
            EventLoop::queue(function () use ($deferred) {
                $deferred->resolve(null);
            });
            return $deferred->promise();
        }
        echo "Starting\n";
        $start = microtime(true);
        $transaction = $this->db->transaction();
        $db = new DbQueryHelper($transaction, $this->logger);
        try {
            $queries = [];
            foreach ($actions as $action) {
                $table = $action->tableName;
                $values = $action->getAllDbValues();
                $keyProperties = $action->getDbKeyProperties();
                async(function () use (&$queries, $db, $action, $table, $values, $keyProperties) {
                $queries[] = match ($action->action) {
                    InventoryActionType::CREATE => $db->insert($table, $values),
                    InventoryActionType::UPDATE => $db->update($table, $action->getDbValuesForUpdate(), $keyProperties),
                    InventoryActionType::DELETE => $db->delete($table, $keyProperties),
                };
                });
                async(function () use (&$queries, $db, $action, $table) {
                    echo $action->streamPosition .  "Insert\n";
                    $queries[] = $db->insert('datanode_table_action_history', [
                        'datanode_uuid'   => $action->sourceNode,
                        'table_name'      => $table,
                        'stream_position' => $action->streamPosition,
                        'action'          => $action->action->value,
                        'key_properties'  => JsonString::encode($action->keyProperties),
                        'sent_values'     => JsonString::encode($action->values),
                    ]);
                    echo "Insert enqueued\n";
                });
                // TODO: $this->logAction?
            }
            /*
            $db->update('datanode_table_sync', [
                'current_position' => $action->streamPosition,
                'current_error' => null,
            ], [
                'datanode_uuid' => $action->sourceNode->getBytes(),
                'table_name'    => $action->tableName, // Hint: works here, but... not so nice
            ]);
            */
            echo "1\n";
            async(function () use ($transaction) {
                echo "Committing\n";
                $transaction->commit();
                echo "Committed\n";
                exit;
            })->finally(function () use ($deferred) {
                echo "NOPE";
                exit;
                $deferred->resolve();
            });
            $this->logger->notice(sprintf(
                'COMMITTED %d queries in %.02fms',
                count($queries),
                (microtime(true) - $start) * 1000
            ));
            $deferred->resolve([$action->streamPosition]);
        } catch (\Throwable $e) {
            $this->logger->error('Transaction failed ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
            $transaction->rollback();
        }

        return $deferred->promise();
    }

}
