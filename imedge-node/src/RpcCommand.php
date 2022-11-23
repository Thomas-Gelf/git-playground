<?php

namespace IcingaDataNode;

use Evenement\EventEmitterTrait;
use Exception;
use gipfl\Process\FinishedProcessState;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\NetString\StreamWrapper;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class RpcCommand
{
    use EventEmitterTrait;

    protected ?JsonRpcConnection $rpc = null;
    protected ?Deferred $waitingForRpc = null;

    public function __construct(
        public readonly string $binary,
        public readonly array $arguments = [],
        public readonly ?string $cwd = null,
        public readonly ?array $env = null,
    ) {
        $this->init();
    }

    protected function init(): void
    {
        $this->on('start', function (Process $process) {
            $netString = new StreamWrapper($process->stdout, $process->stdin);
            $netString->on('error', function (Exception $e) {
                $this->waitingForRpc?->reject($e);
                $this->emit('error', [$e]);
            });
            $this->rpc = new JsonRpcConnection($netString);
            if ($deferred = $this->waitingForRpc) {
                $this->waitingForRpc = null;
                $deferred->resolve($this->rpc);
            }
        });
    }

    protected function prepareCommandString(): string
    {
        $command = ['exec', escapeshellcmd($this->binary)];

        foreach ($this->arguments as $argument) {
            if (ctype_alnum(preg_replace('/^-{1,2}/', '', $argument))) {
                $command[] = $argument;
            } else {
                $command[] = escapeshellarg($argument);
            }
        }

        return implode(' ', $command);
    }

    public function run(): PromiseInterface
    {
        $process = new Process(
            $this->prepareCommandString(),
            $this->cwd,
            $this->env
        );

        $canceller = function () use ($process) {
            // TODO: first soft, then hard
            $process->terminate();
        };
        $deferred = new Deferred($canceller);

        $process->on('exit', function ($exitCode, $termSignal) use ($deferred) {
            $state = new FinishedProcessState($exitCode, $termSignal);
            if ($state->succeeded()) {
                $deferred->resolve();
            } else {
                $deferred->reject(new \RuntimeException($state->getReason()));
            }
        });
        $process->start();
        $this->emit('start', [$process]);

        return $deferred->promise();
    }

    public function rpc(): PromiseInterface
    {
        if (! $this->waitingForRpc) {
            $this->waitingForRpc = new Deferred();
        }

        if ($this->rpc) {
            Loop::futureTick(function () {
                if ($this->rpc && $deferred = $this->waitingForRpc) {
                    $this->waitingForRpc = null;
                    $deferred->resolve($this->rpc);
                }
            });
        }

        return $this->waitingForRpc->promise();
    }
}
