<?php

namespace Icinga\Module\Inventory\Daemon;

use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use function React\Promise\Stream\buffer;

class SshPublicKeyFingerPrinter
{
    /** @var LoopInterface */
    protected $loop;

    /** @var Process */
    protected $process;

    protected $pending = [];

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->loop->addPeriodicTimer(6, function () {
            $this->stopUnusedChildProcess();
        });
    }

    protected function stopUnusedChildProcess()
    {
        if ($this->process !== null && empty($this->pending)) {
            $this->process->close();
            $this->process = null;
        }
    }

    public function getFingerPrint($publicKeyLine)
    {
        return $this->parse(\rtrim($publicKeyLine) . "\n");
    }

    protected function getFingerPrints()
    {
        if ($this->strFingerPrint === '') {
            return reject('Got no fingerprint');
        } else {
            return $this
                ->run('ssh-keygen -lf -', $this->strFingerPrint)
                ->then(function ($exitCode, $stdout, $stderr) {
                    if ($exitCode === 0) {
                        $this->processPubkeys($stdout);
                    }
                });
        }
    }

    protected function processPubkeys($line)
    {
        $parts = \preg_split('/ /', $line);
        if (count($parts) === 4) {
            return (object) [
                'type'        => strtolower(ltrim(rtrim($parts[3], ')'), '(')),
                'bits'        => $parts[0],
                'fingerprint' => $parts[1],
                'hostList'    => $parts[2],
            ];
        } else {
            // log("ERROR: Invalid fingerprint '$line'\n");
            return false;
        }
    }

    protected function parse($line)
    {
        $deferred = new Deferred();
        $this->process()->stdin->write($line);

        return $deferred->promise();
    }

    protected function process()
    {
        if ($this->process === null) {
            $this->process = $this->run('ssh-keygen -lf -');
        }

        return $this->process;
    }

    protected function run($command)
    {
        $process = new Process($command);
        $process->start($this->loop);

        $stdout = null;
        $stderr = null;
        $deferred = new Deferred();
        $eventuallyResolve = function () use (&$stdout, &$stderr, &$exitCode, $deferred) {
            if ($stdout !== null && $stderr !== null && $exitCode !== null) {
                return;
            }

            $deferred->resolve([$stdout, $stderr, $exitCode]);
        };

        buffer($process->stdout)->then(function ($data) use (&$stdout, $eventuallyResolve) {
            $stdout = $data;
            $eventuallyResolve();
        });
        buffer($process->stderr)->then(function ($data) use (&$stderr, $eventuallyResolve) {
            $stderr = $data;
            $eventuallyResolve();
        });
        $exitCode = null;
        $process->on('exit', function ($code, $term) use (&$exitCode, $eventuallyResolve) {
            if ($term === null) {
                $exitCode = $code;
            } else {
                $exitCode = 127 + $term; // TODO: Check this.
            }
            $eventuallyResolve();
        });

        return $deferred->promise();
    }
}
