<?php

namespace IcingaDataNode\Redis;

use Clue\React\Redis\Client;
use Exception;
use gipfl\Json\JsonString;
use gipfl\RedisUtils\RedisUtil;
use IcingaDataNode\DataNode;
use IcingaDataNode\DataNodeFeatureInstance;
use IcingaDataNode\Inventory\CentralInventory;
use IcingaDataNode\Inventory\InventoryAction;
use IcingaDataNode\Inventory\InventoryActionType;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;

use function React\Promise\Timer\sleep;

final class RedisTableSubscriber
{
    protected ?Client $redis = null;
    protected ?CentralInventory $inventory = null;
    protected string $streamPosition = '0-0';
    protected bool $stopping = false;

    public function __construct(
        protected readonly string $tableName,
        protected readonly DataNode|DataNodeFeatureInstance $dataNode,
        protected readonly LoggerInterface $logger,
    ) {}

    public function setStreamPosition(string $position): void
    {
        $this->streamPosition = $position;
    }

    protected function waitForRedis(): void
    {
        if ($this->inventory === null || $this->stopping) {
            return;
        }
        $this->dataNode->services->getRedisClient('tableSubscriber/' . $this->tableName)
            ->then(function (Client $client) {
                if ($this->inventory === null) {
                    $client->close();
                    return;
                }
                $this->redis = $client;
                $client->on('close', function () {
                    $this->redis = null;
                });
                Loop::futureTick($this->processNextScheduledBatch(...));
            });
    }

    protected function processStreamResult($result): void
    {
        if (empty($result) || $this->inventory === null) {
            Loop::futureTick($this->processNextScheduledBatch(...));
            return;
        }
        // $this->logger->notice('GOT A STREAM RESULT: ' . count($result));
        $actions = [];
        foreach ($result as $row) {
            try {
                $table = $row[0]; // snmp_agent-changes
                foreach ($row[1] as $entry) {
                    $this->streamPosition = $entry[0];
                    $rawData = RedisUtil::makeHash($entry[1]);
                    $values = JsonString::decode($rawData->value);
                    $keyProperties = JsonString::decode($rawData->keyProperties);
                    foreach ($values as &$value) { // Fix for erroneous serialization
                        if (is_object($value) && isset($value->oid)) {
                            $value = $value->oid;
                        }
                    }
                    unset($value);
                    $action = new InventoryAction(
                        $this->dataNode->getUuid(),
                        $this->tableName,
                        $this->streamPosition,
                        InventoryActionType::from($rawData->action),
                        $rawData->key,
                        $rawData->checksum ?? null,
                        $keyProperties,
                        (array) $values,
                    );
                    $actions[] = $action;
                }
                // print_r($actions);
                // echo "ACTIONS HERE\n\n";
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage() . $e->getFile() . $e->getLine());
            }
        }
        if (!empty($actions)) {
            try {
                $this->inventory->shipBulkActions($actions);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to ship: ' . $e->getMessage() . $e->getFile() . $e->getLine());
            }
        }
        Loop::futureTick($this->processNextScheduledBatch(...));
    }

    protected function processNextScheduledBatch(): void
    {
        if ($this->redis === null) {
            return;
        }

        $this->readNextBatch($this->tableName . '-changes')->then(function ($stream) {
            $this->processStreamResult($stream);
        }, function (Exception $e) {
            if ($this->stopping) {
                return;
            }
            $this->logger->error(sprintf(
                'Reading next batch for %s-changes failed, continuing in 15s: %s',
                $this->tableName,
                $e->getMessage()
            ));
            sleep(15)->then(function () {
                Loop::futureTick($this->processNextScheduledBatch(...));
            });
        });
    }

    protected function readNextBatch($stream): PromiseInterface
    {
        $blockMs = 1000;
        $maxCount = 1000;
        // $maxCount = 10;
        return $this->readFromStream($stream, $this->streamPosition, $maxCount, $blockMs);
    }


    public function readFromStream($stream, $position, $maxCount, $blockMs): PromiseInterface
    {
        return $this->redis->xread(
            'COUNT',
            (string) $maxCount,
            'BLOCK',
            (string) $blockMs,
            'STREAMS',
            $stream,
            $position
        );
    }

    public function setCentralInventory(CentralInventory $inventory): void
    {
        $this->inventory = $inventory;
        if ($this->redis) {
            $this->redis->close();
            $this->redis = null;
        }
        // $this->logger->notice('Hey, got an inventory');

        $this->waitForRedis();
    }

    public function stop(): void
    {
        $this->stopping = true;
        if ($this->redis) {
            $this->redis->close();
            $this->redis = null;
        }
        $this->inventory = null;
    }
}
