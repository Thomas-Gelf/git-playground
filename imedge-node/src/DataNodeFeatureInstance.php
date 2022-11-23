<?php

namespace IcingaDataNode;

use IcingaDataNode\Inventory\CentralInventory;
use IcingaDataNode\Redis\RedisTableSubscriber;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

/**
 * Used by Sub-Processes, not being full nodes, but having their own node UUIDs
 */
class DataNodeFeatureInstance
{
    const SOCKET_PATH = '/run/imedge-node';

    protected Features $features;
    public readonly NodeIdentifier $identifier;
    public readonly Events $events;
    public readonly Services $services;

    /** @var RedisTableSubscriber[] */
    protected array $redisTableSubscribers = [];

    public function __construct(
        protected readonly UuidInterface $uuid,
        protected readonly string $name,
        protected readonly string $fqdn,
        protected readonly LoggerInterface $logger,
    ) {
    }

    protected function initialize(): void
    {
        $this->events = new Events();
        $this->identifier = new NodeIdentifier(
            $this->uuid,
            $this->name,
            $this->fqdn
        );
    }

    public function stop(): PromiseInterface
    {
        return resolve(null);
    }

    public function setCentralInventory(CentralInventory $inventory): void
    {
        $this->logger->notice(sprintf(
            'Got Central Inventory (%s): %s',
            $this->uuid->toString(),
            get_class($inventory))
        );
        foreach ($this->redisTableSubscribers as $subscriber) {
            $subscriber->stop();
        }
        $this->redisTableSubscribers = [];
        $tables = $inventory->loadTableSyncPositions($this->identifier);
        $this->logger->notice('Got tables: ' . count($tables));
        foreach ($tables as $table => $position) {
            $this->logger->notice('LISTENING to table ' . $table);
            $this->redisTableSubscribers[$table] = new RedisTableSubscriber(
                $table,
                $this,
                $this->logger
            );
            $this->redisTableSubscribers[$table]->setStreamPosition($position);
        }
        foreach ($this->redisTableSubscribers as $subscriber) {
            $subscriber->setCentralInventory($inventory);
        }
    }

    public function restart(): void
    {
    }
}
