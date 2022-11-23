<?php

namespace IcingaDataNode;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use IcingaDataNode\Redis\RedisTables;
use Psr\Log\LoggerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Revolt\EventLoop;

use function React\Promise\reject;
use function React\Promise\Timer\timeout;

class RedisServices
{
    public function __construct(
        protected string $redisSocket,
        protected LoggerInterface $logger,
    ) {}

    /**
     * @return PromiseInterface<Client>
     */
    public function getRedisClient(string $clientName): PromiseInterface
    {
        $this->logger->notice("(RedisServices) Getting Redis Client for $clientName");
        return $this->waitForRedis()->then(function (Client $client) use ($clientName) {
            $client->on('error', function (\Exception $e) use ($clientName) {
                $this->logger->error("(RedisServices) Redis Client for $clientName failed: " . $e->getMessage());
            });
            return $client->client('setname', $clientName)->then(function () use ($client) {
                return $client;
            });
        });
    }

    /**
     * @return PromiseInterface<RedisTables>
     */
    public function getRedisTables(string $clientName): PromiseInterface
    {
        $this->logger->notice("(RedisServices) Getting Redis table for $clientName");
        return $this->getRedisClient($clientName)->then(function (Client $client) use ($clientName) {
            $this->logger->notice("(RedisServices) RedisTables ready for $clientName");
            try {
                $result = new RedisTables($client, $this->logger);
            } catch (\Throwable $e) {
                $this->logger->error(
                    "(RedisServices) RedisTables initialization failed for $clientName: " . $e->getMessage()
                );

                return reject($e);
            }
            return $result;
        });
    }

    protected function waitForRedis(): PromiseInterface
    {
        $timer = null;
        $deferred = new Deferred(function () use (&$timer) {
            if ($timer) {
                EventLoop::cancel($timer);
            }
        });
        $logged = false;
        $function = function () use ($deferred, &$timer, &$logged) {
            if ($this->redisSocket && file_exists($this->redisSocket)) {
                $this->logger->notice('Socket ' . $this->redisSocket . ' exists, initializing Redis client');
                if ($timer) {
                    EventLoop::cancel($timer);
                }
                $factory = new Factory();
                $deferred->resolve($factory->createClient('redis+unix://' . $this->redisSocket));
            } else {
                if (! $logged) {
                    $logged = true;
                    $this->logger->warning(
                        'Redis socket does not exist, retrying 10 times a second for not more than 10 seconds: '
                        . $this->redisSocket
                    );
                }
            }
        };
        $timer = EventLoop::repeat(0.1, $function);

        return timeout($deferred->promise(), 10);
    }
}
