<?php

namespace IcingaDataNode\Redis;

use Clue\React\Redis\Client;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use gipfl\Json\JsonString;
use gipfl\RedisUtils\LuaScriptRunner;
use gipfl\RedisUtils\RedisUtil;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

class RedisTables implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected LuaScriptRunner $lua;

    public function __construct(
        protected readonly Client $redis,
        protected readonly LoggerInterface $logger,
    ) {
        $redis->on('close', function () {
            $this->emit('close');
        });
        $this->lua = new LuaScriptRunner($this->redis, dirname(__DIR__, 2) . '/lua');
        $this->lua->setLogger($this->logger);
    }

    public function setTableForDevice(
        string $table,
        string $devicePrefix,
        array $keyProperties,
        array $tables
    ): PromiseInterface {
        $tables = array_map(function ($row) {
            $json = JsonString::encode($row);
            return sha1($json) . $json;
        }, $tables);

        return $this->lua->runScript('setTable', [
            $table,
            40, // strlen($checksum)
            $devicePrefix,
            JsonString::encode($keyProperties),
        ], self::arrayToLuaTable($tables))->then(RedisUtil::makeHash(...))->then(fn ($result) => $result->status);
    }

    protected static function arrayToLuaTable(array $array): array
    {
        $result = [];
        foreach ($array as $k => $v) {
            $result[] = $k;
            $result[] = $v;
        }

        return $result;
    }

    public function setTableEntry(string $table, string $key, array $keyProperties, mixed $data): PromiseInterface
    {
        $json = JsonString::encode($data);
        $checksum = sha1($json);
        return $this->lua->runScript('setTableEntry', [
            $table,
            40, // strlen($checksum)
            $key,
            JsonString::encode($keyProperties)
        ], [
            $checksum . $json,
        ])->then(RedisUtil::makeHash(...))->then(fn ($result) => $result->status);
    }

    public function getTable(string $table): PromiseInterface
    {
        return $this->lua->runScript('getTable', [$table])->then(function ($result) {
            $this->logger->notice(var_export($result, 1));
            if ($result !== null) {
                $result[1] = array_map(JsonString::decode(...), (array) RedisUtil::makeHash($result[1]));
            }

            return $result;
        });
    }
}
