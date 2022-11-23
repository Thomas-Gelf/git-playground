<?php

namespace IcingaDataNode\Redis;

use Amp\Redis\RedisClient;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use gipfl\Json\JsonString;
use gipfl\RedisUtils\RedisUtil;
use IMEdge\RedisUtils\LuaScriptRunner;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

class NewRedisTables implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected LuaScriptRunner $lua;

    public function __construct(
        protected readonly RedisClient $redis,
        protected readonly LoggerInterface $logger,
    ) {
        $this->lua = new LuaScriptRunner($this->redis, dirname(__DIR__, 2) . '/lua', $this->logger);
    }

    public function getTable(string $table): PromiseInterface
    {
        $result = $this->lua->runScript('getTable', [$table]);
        $this->logger->notice(var_export($result, 1));
        if ($result !== null) {
            $result[1] = array_map(JsonString::decode(...), RedisUtil::makeArray($result[1]));
        }

        return $result;
    }

    public function setTableForDevice(
        string $table,
        string $devicePrefix,
        array $keyProperties,
        array $tables
    ) {
        $tables = array_map(self::createTableEntry(...), $tables);

        return RedisUtil::makeHash($this->lua->runScript('setTable', [
            $table,
            40, // strlen($checksum)
            $devicePrefix,
            JsonString::encode($keyProperties),
        ], self::arrayToLuaTable($tables)))->status;
    }

    public function setTableEntry(
        string $table,
        string $key,
        array $keyProperties,
        mixed $data
    ): PromiseInterface
    {
        return RedisUtil::makeHash($this->lua->runScript('setTableEntry', [
            $table,
            40, // strlen($checksum)
            $key,
            JsonString::encode($keyProperties)
        ], [
            self::createTableEntry($data),
        ]))->status;
    }

    protected static function createTableEntry($row): string
    {
        $json = JsonString::encode($row);
        return sha1($json) . $json;
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
}
