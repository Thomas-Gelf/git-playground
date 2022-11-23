<?php

namespace IcingaMetrics;

use Clue\React\Redis\Client as RedisClient;
use Clue\React\Redis\Factory as RedisFactory;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Exception;
use gipfl\Json\JsonString;
use gipfl\ReactUtils\RetryUnless;
use gipfl\RedisUtils\LuaScriptRunner;
use gipfl\RedisUtils\RedisUtil;
use gipfl\RrdTool\DsList;
use gipfl\RrdTool\RraSet;
use IcingaDataNode\Monitoring\Ci;
use IcingaDataNode\Monitoring\Measurement;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;
use function time;

class RedisPerfDataApi implements EventEmitterInterface
{
    use EventEmitterTrait;

    // const ON_PERF_DATA = 'perfData';
    const ON_MEASUREMENTS = 'measurements';
    const ON_STRAIN_START = 'strain_start';
    const ON_STRAIN_END = 'strain_end';
    const STRAIN_START = 100000;
    const STRAIN_END = 5000;

    /** @var PromiseInterface|RedisClient|null */
    protected $redis = null;
    /** @var LuaScriptRunner|PromiseInterface|null */
    protected $lua = null;
    protected LoggerInterface $logger;
    protected string $socketUri;
    protected string $luaDir;
    // protected string $prefix = 'rrd:';
    protected string $prefix = 'metrics:';
    protected string $clientName = ApplicationFeature::PROCESS_NAME;
    protected int $cntPending = 0;
    protected bool $isStrain = false;

    public function __construct(LoggerInterface $logger, $redisSocketUri)
    {
        $this->logger = $logger;
        $this->socketUri = $redisSocketUri;
        $this->luaDir = dirname(__DIR__) . '/lua';
    }

    public function setClientName($name): RedisPerfDataApi
    {
        $this->clientName = $name;
        if ($this->redis instanceof RedisClient) {
            $this->redis->client('setname', $this->clientName)->catch(function (Exception $e) {
                $this->logger->error(sprintf(
                    'Setting client name %s failed: %s',
                    $this->clientName,
                    $e->getMessage()
                ));
            });
        }

        return $this;
    }

    public function getRedisConnection(): PromiseInterface
    {
        if ($this->redis === null) {
            $this->logger->debug('Initiating a new Redis connection');
            $this->redis = $this->keepConnectingToRedis()->then(function (RedisClient $client) {
                $this->logger->notice('CONNECTED to Redis: ' . $this->clientName);
                $this->redis = $client;
                return $client;
            }, function ($e) {
                $this->logger->error('Failed to get Redis connection: ' . $e->getMessage());
            });
            return $this->redis;
        }

        // Hint: it's a StreamingClient
        if (! $this->redis instanceof RedisClient) {
            $this->logger->info(sprintf(
                'Redis is still a %s (%s - connecting to %s)',
                get_class($this->redis),
                $this->clientName,
                $this->socketUri
            ));
            return $this->redis;
        }

        // $this->logger->notice('Resolving redis ' . $this->clientName . ' with ' . get_class($this->redis));

        return resolve($this->redis);
    }

    public function getLuaRunner(): PromiseInterface
    {
        if ($this->lua === null) {
            $deferred = new Deferred();
            $this->lua = $deferred->promise();
            $this->getRedisConnection()->then(function (RedisClient $client) use ($deferred) {
                $lua = new LuaScriptRunner($client, $this->luaDir);
                $lua->setLogger($this->logger);
                $this->lua = $lua;
                $deferred->resolve($lua);
            }, function (Exception $e) {
                $this->logger->error('Instantiating LUA runner failed, this should not happen: ' . $e->getMessage());
            });
        }

        return resolve($this->lua);
    }

    protected function incPending($count = 1): void
    {
        $this->cntPending += $count;
        // $this->logger->debug('Pending: ' . $this->cntPending . ' after ' . $count);
        if ($this->isStrain) {
            if ($this->cntPending < self::STRAIN_END) {
                $this->isStrain = false;
                $this->emit(self::ON_STRAIN_END, [$this->cntPending]);
            }
        } else {
            if ($this->cntPending >= self::STRAIN_START) {
                $this->isStrain = true;
                $this->emit(self::ON_STRAIN_START, [$this->cntPending]);
            }
        }
    }

    public function shipMeasurement(Measurement $measurement): PromiseInterface
    {
        // TODO: Really? Or has a single submission more result details?
        return $this->shipMeasurements([$measurement])->then(function ($result) {
            return $result[0];
        });
    }

    /**
     * @param Measurement[] $measurements
     * @return PromiseInterface
     */
    public function shipMeasurements(array $measurements): PromiseInterface
    {
        $count = count($measurements);
        $this->incPending($count);
        return $this->getLuaRunner()->then(function (LuaScriptRunner $lua) use ($measurements, $count) {
            // TODO: ship prefix?!
            $lua->runScript('shipMeasurements', array_map(JsonString::encode(...), $measurements))
                ->then(RedisUtil::makeHash(...))->finally(function () use ($count) {
                    $this->incPending(-$count);
                });
        });
    }

    protected function keepConnectingToRedis()
    {
        $deferred = new Deferred();
        $retry = RetryUnless::succeeding(function () use ($deferred) {
            return $this->connectToRedis()->then(function (RedisClient $client) use ($deferred) {
                $deferred->resolve($client);
                return $client;
            });
        })->slowDownAfter(10, 5);
        $retry->setLogger($this->logger);
        $retry->run(Loop::get());

        return $deferred->promise();
    }

    public function connectToRedis()
    {
        $factory = new RedisFactory(Loop::get());
        return $factory
            ->createClient($this->socketUri)
            ->then(function (RedisClient $client) {
                return $this->redisIsReady($client);
            }, function (Exception $e) {
                $this->logger->error('Connection error: ' . $e->getMessage());
                if ($previous = $e->getPrevious()) {
                    throw new \RuntimeException($e->getMessage() . ': ' . $previous->getMessage(), 0, $e);
                }

                throw $e;
            });
    }

    public function getCounters(): PromiseInterface
    {
        return $this->getRedisConnection()->then(function (RedisClient $redis) {
            return $redis->hgetall($this->prefix . 'counters');
        })->then(function ($result) {
            if (empty($result)) {
                return reject(new Exception('Redis currently has no counters'));
            }
            return RedisUtil::makeHash($result);
        });
    }

    public function readFromStream($stream, $position, $maxCount, $blockMs): PromiseInterface
    {
        return $this->getRedisConnection()
            ->then(function (RedisClient $client) use ($position, $maxCount, $blockMs, $stream) {
                return $client->xread(
                    'COUNT', (string) $maxCount,
                    'BLOCK', (string) $blockMs,
                    'STREAMS', $stream, $position,
                );
            });
    }

    public function fetchBatchFromStream($position, $maxCount, $blockMs): PromiseInterface
    {
        return $this->readFromStream($this->prefix . 'stream', $position, $maxCount, $blockMs);
    }

    public function fetchLastPosition(): PromiseInterface
    {
        return $this->getRedisConnection()->then(function ($client) {
            return $client->get($this->prefix . 'stream-last-pos');
        });
    }

    public function setLastPosition($position): PromiseInterface
    {
        return $this->getRedisConnection()->then(function (RedisClient $client) use ($position) {
            return $client->set($this->prefix . 'stream-last-pos', $position);
        });
    }

    public function fetchBatchFromCiUpdateStream($position, $maxCount, $blockMs): PromiseInterface
    {
        return $this->readFromStream($this->prefix . 'ci-changes', $position, $maxCount, $blockMs);
    }

    public function fetchLastCiUpdatePosition(): PromiseInterface
    {
        return $this->getRedisConnection()->then(function ($client) {
            return $client->get($this->prefix . 'ci-stream-last-pos');
        });
    }

    public function setLastCiUpdatePosition($position): PromiseInterface
    {
        return $this->getRedisConnection()->then(function (RedisClient $client) use ($position) {
            return $client->set($this->prefix . 'ci-stream-last-pos', $position);
        });
    }

    public function setCiConfigOnly(Ci $ci, CiConfig $config): PromiseInterface
    {
        $ciName = JsonString::encode($ci);
        $this->logger->debug("Registering $ciName in Redis");
        return $this->hSet('ci', $ciName, JsonString::encode($config));
    }

    public function setCiConfig($ci, CiConfig $config, DsList $dsList, RraSet $rraSet)
    {
        $this->logger->debug("Registering $ci in Redis");
        $json =  JsonString::encode($config);
        return all([
            $this->xAdd(
                'ci-changes',
                'MAXLEN',
                '~',
                500_000,
                '*',
                'ci',
                $ci,
                'config',
                $json,
                'ds',
                (string) $dsList,
                'rra',
                (string) $rraSet
            ),
            $this->hSet('ci', $ci, $json),
        ]);
    }

    public function deferCi(Ci $ci, string $reason): PromiseInterface
    {
        return $this->hSet('deferred-cids', JsonString::encode($ci), JsonString::encode([
            'reason' => $reason,
            'since'  => time()
        ]));
    }

    public function rescheduleDeferredCi(Ci $ci): PromiseInterface
    {
        return $this->getLuaRunner()->then(function (LuaScriptRunner $lua) use ($ci) {
            return $lua->runScript('processDeferredCi', [JsonString::encode($ci)]);
            // return $lua->runScript('rescheduleDeferredCi', [JsonString::encode($ci)]);
        });
    }

    public function fetchDeferred(): PromiseInterface
    {
        return $this->fetchSetAsArray('deferred-ci');
    }

    public function fetchDeferredNew(): PromiseInterface
    {
        return all([
            'missing-ci' => $this->fetchSetAsArray('missing-ci'),
            'missing-ds' => $this->fetchSetAsArray('missing-ds')
        ]);
    }

    public function disconnect(): void
    {
        $this->redis?->end();
    }

    protected function hSet($hash, $key, $value): PromiseInterface
    {
        return $this->getRedisConnection()->then(function (RedisClient $client) use ($hash, $key, $value) {
            return $client->hset($this->prefix . $hash, $key, $value);
        });
    }

    protected function xAdd($stream, ...$args): PromiseInterface
    {
        return $this->getRedisConnection()->then(function (RedisClient $client) use ($stream, $args) {
            return $client->xadd($this->prefix . $stream, ...$args);
        });
    }

    protected function fetchSetAsArray($key): PromiseInterface
    {
        return $this->getRedisConnection()->then(function (RedisClient $client) use ($key) {
            return $client->hgetall($this->prefix . $key)->then(RedisUtil::makeArray(...));
        });
    }

    /**
     * @param array $ciList
     * @return PromiseInterface<array>
     */
    public function getCiConfigs(array $ciList): PromiseInterface
    {
        return $this->getRedisConnection()->then(function (RedisClient $client) use ($ciList) {
            return $client->hmget($this->prefix . 'ci', ...$ciList)->then(function ($result) {
                return array_map(function ($entry) {
                    return CiConfig::fromSerialization($entry);
                }, RedisUtil::makeArray($result));
            });
        });
    }

    protected function redisIsReady(RedisClient $client)
    {
        $client->on('end', function () {
            $this->logger->info('Redis ended');
        });
        $client->on('error', function (Exception $e) {
            $this->redis = null;
            $this->logger->error('Redis error: ' . $e->getMessage());
        });

        $client->on('close', function () {
            $this->redis = null;
            $this->logger->info('Redis closed');
        });

        $this->redis = $client;

        return $client->client('setname', $this->clientName)->then(function () {
            return $this->redis;
        });
    }
}
