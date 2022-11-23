<?php

namespace IcingaDataNode;

use Amp\Redis\RedisClient;
use Clue\React\Redis\Client;
use IcingaDataNode\Redis\NewRedisTables;
use IcingaDataNode\Redis\RedisTables;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

use function React\Promise\reject;

class Services
{
    public function __construct(
        protected DataNode $dataNode,
        protected LoggerInterface $logger,
    ) {}

    /**
     * @return PromiseInterface<Client>
     */
    public function getRedisClient(string $clientName): PromiseInterface
    {
        $this->logger->notice("Getting Redis client for $clientName");
        return $this->dataNode->waitForRedis()->then(function (Client $client) use ($clientName) {
            $client->on('error', function (\Exception $e) use ($clientName) {
                $this->logger->error("Redis client for $clientName failed: " . $e->getMessage());
            });
            return $client->client('setname', $clientName)->then(function () use ($client) {
                return $client;
            })->catch(function () {
                $this->logger->error('WHÄÄÄ?');
            });
        });
    }

    public function getNewRedisClient(string $clientName): RedisClient
    {
        $this->logger->notice("Getting (new) Redis client for $clientName");
        $client = $this->dataNode->getRedisClient();
        $client->execute('CLIENT', 'SETNAME', $clientName);

        return $client;
    }

    /**
     * @return PromiseInterface<RedisTables>
     */
    public function getRedisTables(string $clientName): PromiseInterface
    {
        $this->logger->notice("Getting Redis table for $clientName");
        return $this->getRedisClient($clientName)->then(function (Client $client) use ($clientName) {
            $this->logger->notice("RedisTables ready for $clientName");
            try {
                $result = new RedisTables($client, $this->logger);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to initialize redis tables for connection: ' . $e->getMessage());
                return reject($e);
            }

            return $result;
        });
    }

    public function getNewRedisTables(string $clientName): NewRedisTables
    {
        $this->logger->notice("Getting (new) Redis table for $clientName");
        $client = $this->getNewRedisClient($clientName);
        $this->logger->notice("RedisTables ready for $clientName");
        return new NewRedisTables($client, $this->logger);
    }
}
