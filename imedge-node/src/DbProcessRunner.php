<?php

namespace IcingaDataNode;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use gipfl\Process\FinishedProcessState;
use gipfl\Process\ProcessKiller;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use Psr\Log\LoggerInterface;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stream\Util;
use RuntimeException;
use function React\Promise\Timer\timeout;

class DbProcessRunner implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected ?JsonRpcConnection $rpc = null;
    protected ?LogProxy $logProxy = null;
    protected ?Process $process =  null;

    protected array $queue = [];

    public function __construct(
        protected readonly LoggerInterface $logger
    ) {}

    public function stop(): void
    {
        if ($this->process) {
            $process = $this->process;
            $this->process = null;
            if ($process->isRunning()) {
                ProcessKiller::terminateProcess($process, Loop::get(), 2);
            }
        }
    }

    protected function removeProcess(): void
    {
        $this->process = null;
        $this->rpc = null;
    }

    public function request($method, $params = []): PromiseInterface
    {
        // return $this->rpc->request($method, $params);
        if ($this->rpc === null) {
            throw new RuntimeException('Process RPC is not ready');
        }

        $deferred = new Deferred();
        $this->queue[] = [$deferred, $method, $params];
        $this->scheduleNextRequest();
        return $deferred->promise();
    }

    protected function scheduleNextRequest(): void
    {
        Loop::futureTick(function () {
            $this->sendNextRequest();
        });
    }

    protected function sendNextRequest(): void
    {
        if (empty($this->queue)) {
            return;
        }
        $next = array_shift($this->queue);
        /** @var Deferred $deferred */
        $deferred = $next[0];

        $this->rpc->request($next[1], $next[2])->then(function ($result) use ($deferred) {
            $deferred->resolve($result);
            $this->scheduleNextRequest();
        }, function ($e) use ($deferred) {
            $this->scheduleNextRequest();
            $deferred->reject($e);
        });
    }

    protected function rejectQueue(\Exception $e): void
    {
        foreach ($this->queue as $entry) {
            $entry[0]->reject($e);
        }
        $this->queue = [];
    }

    public function run()
    {
        if ($this->process) {
            throw new RuntimeException('Process is already running');
        }
        $command = new RpcCommand('icinga-datanode', ['db']);
        $command->on('start', function (Process $process) {
            $this->process = $process;
            $process->on('exit', function ($exitCode, $termSignal) {
                // If there is no process, we have been stopped
                if ($this->process) {
                    $message = (new FinishedProcessState($exitCode, $termSignal))->getReason();
                    $this->removeProcess();
                    $this->rejectQueue(new \Exception($message));
                    $this->logger->error($message);
                    $this->emit('error', [new \Exception($message)]);
                }
            });
        });
        Util::forwardEvents($command, $this, ['error']);

        $this->logProxy = new LogProxy($this->logger);
        $this->logProxy->setPrefix("[db] ");
        $promise = timeout($command->rpc(), 10)->then(function (JsonRpcConnection $rpc) {
            $handler = new NamespacedPacketHandler();
            $handler->registerNamespace('logger', $this->logProxy);
            $rpc->setHandler($handler);
            $this->rpc = $rpc;
        });

        $command->run();

        return $promise;
    }
}
