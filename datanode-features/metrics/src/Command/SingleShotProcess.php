<?php

namespace IcingaMetrics\Command;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Exception;
use gipfl\Process\FinishedProcessState;
use gipfl\Process\ProcessKiller;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RuntimeException;

class SingleShotProcess implements EventEmitterInterface
{
    use EventEmitterTrait;

    public ?FinishedProcessState $state = null;
    protected Process $process;
    protected Deferred $deferred;
    protected string $stdout = '';
    protected string $stderr = '';

    public function __construct(Process $process)
    {
        $this->process = $process;
        $this->deferred = new Deferred(function () {
            ProcessKiller::terminateProcess($this->process, Loop::get());
        });
        $process->on('exit', function ($exitCode, $termSignal) {
            $state = $this->state = new FinishedProcessState($exitCode, $termSignal);
            if ($state->succeeded()) {
                $this->deferred->resolve($this);
            } else {
                $this->deferred->reject(new Exception($state->getReason()));
            }
        });
    }

    public function getFullStdout(): string
    {
        if ($this->process->isRunning()) {
            throw new RuntimeException('Process is still running');
        }

        return $this->stdout;
    }

    public function getFullStderr(): string
    {
        if ($this->process->isRunning()) {
            throw new RuntimeException('Process is still running');
        }

        return $this->stderr;
    }

    public function promise(): PromiseInterface
    {
        $process = $this->process;
        $process->start();
        $process->stdout->on('data', function ($data) {
            $this->stdout .= $data;
        });
        $process->stderr->on('data', function ($data) {
            $this->stderr .= $data;
        });
        return $this->deferred->promise();
    }
}
