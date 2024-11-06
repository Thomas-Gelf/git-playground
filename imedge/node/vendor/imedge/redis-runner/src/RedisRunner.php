<?php

namespace IMEdge\RedisRunner;

use Amp\DeferredFuture;
use Amp\Process\Process;
use IMEdge\Filesystem\Directory;
use IMEdge\ProcessRunner\BufferedLineReader;
use IMEdge\ProcessRunner\ProcessRunnerHelper;
use Revolt\EventLoop;
use RuntimeException;

use function Amp\Future\await;

class RedisRunner extends ProcessRunnerHelper
{
    protected string $applicationName = 'Redis/ValKey';
    protected string $redisSocket;

    public function getRedisSocket(): string
    {
        return $this->redisSocket;
    }

    protected function initialize(): void
    {
        $this->redisSocket = $this->baseDir . '/redis.sock';
    }

    protected function onStartingProcess(): void
    {
        Directory::requireWritable($this->baseDir);
        $socket = $this->getRedisSocket();
        if (file_exists($socket)) {
            $this->logger->notice(sprintf("Orphaned %s Socket found in %s, removing", $this->applicationName, $socket));
            unlink($socket);
        }
        file_put_contents($this->getConfigFilename(), RedisConfigGenerator::forPath($this->baseDir));
    }

    protected function onProcessStarted(Process $process): void
    {
        $deferred = new DeferredFuture();
        EventLoop::queue(function () use ($deferred, $process) {
            $reader = new BufferedLineReader(static function (string $line) use ($deferred) {
                if (preg_match('/The server is now ready to accept connections at (.+?)$/', $line, $match)) {
                    $deferred->complete($match[1]);
                }
                // TODO: only if asked for $this->logger->info($data);
                // $this->logger->info($line);
            }, "\n");
            while ($chunk = $process->getStdout()->read()) {
                $reader->write($chunk);
            }
        });
        $socket = await([$deferred->getFuture()])[0];
        if ($socket !== $this->redisSocket) {
            throw new RuntimeException(sprintf(
                'Expecting %s to listen on %s, not on %s',
                $this->applicationName,
                $this->redisSocket,
                $socket
            ));
        }
    }

    protected function getArguments(): array // -> redis
    {
        return [$this->getConfigFilename()];
    }

    protected function getConfigFilename(): string
    {
        return $this->baseDir . '/redis.conf';
    }
}
