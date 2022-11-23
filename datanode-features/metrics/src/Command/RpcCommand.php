<?php

namespace IcingaMetrics\Command;

use Exception;
use gipfl\Process\FinishedProcessState;
use gipfl\Protocol\JsonRpc\Handler\JsonRpcHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\NetString\StreamWrapper;
use Psr\Log\LoggerInterface;
use React\ChildProcess\Process;

class RpcCommand
{
    public static function run(Process $process, JsonRpcHandler $handler, LoggerInterface $logger): JsonRpcConnection
    {
        $process->start();
        $netString = new StreamWrapper($process->stdout, $process->stdin);
        $connection = new JsonRpcConnection($netString, $handler);
        $process->on('exit', function ($exitCode, $termSignal) use ($connection) {
            $state = new FinishedProcessState($exitCode, $termSignal);
            if (!$state->succeeded()) {
                $connection->emit('error', [new Exception($state->getReason())]);
            }
            $connection->close();
        });

        return $connection;
    }
}
