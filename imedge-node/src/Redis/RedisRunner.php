<?php

namespace IcingaDataNode\Redis;

use Amp\DeferredFuture;
use Amp\Process\Process;
use IcingaDataNode\FilesystemUtil;
use IcingaDataNode\Process\BufferedLineReader;
use IcingaDataNode\Process\ProcessWithPidInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use RuntimeException;

use function Amp\Future\await;

class RedisRunner implements ProcessWithPidInterface
{
    protected ?Process $process = null;
    protected string $baseDir;
    protected string $binary;
    protected LoggerInterface $logger;
    protected ?int $redisPid;

    public function __construct(string $binary, string $baseDir, LoggerInterface $logger)
    {
        if (! is_executable($binary)) {
            throw new RuntimeException("Cannot execute $binary");
        }
        $this->binary = $binary;
        $this->baseDir = $baseDir;
        $this->logger = $logger;
    }

    public function getProcessPid(): ?int
    {
        return $this->redisPid;
    }

    public function run(): string
    {
        $deferred = new DeferredFuture();
        FilesystemUtil::requireDirectory($this->baseDir);
        $sockPath = $this->baseDir . '/redis.sock';
        if (file_exists($sockPath)) {
            $this->logger->notice("Orphaned Redis/Valkey Socket found in $sockPath, removing");
            unlink($sockPath);
        }

        EventLoop::queue(function () use ($deferred) {
            $dir = $this->baseDir;
            $redisConf = "$dir/redis.conf";
            file_put_contents($redisConf, RedisConfigGenerator::forPath($dir));

            // setsid avoids INT and other signals trickling down
            $runner = Process::start(['setsid', $this->binary, $redisConf]);
            $this->process = $runner;
            $this->redisPid = $runner->getPid();
            EventLoop::queue(function () use ($deferred) {
                $reader = new BufferedLineReader(static function (string $line) use ($deferred) {
                    if (preg_match('/The server is now ready to accept connections at (.+?)$/', $line, $match)) {
                        $deferred->complete($match[1]);
                    }
                    // TODO: only if asked for $this->logger->info($data);
                    // $this->logger->info($line);
                }, "\n");
                while ($chunk = $this->process->getStdout()->read()) {
                    $reader->write($chunk);
                }
            });
            // TODO: here we might want to restart the process, if terminated
            // Missing: information related to exit code
        });

        return await([$deferred->getFuture()])[0];
    }

    public function stop(): void
    {
        $deferred = new DeferredFuture();
        if ($this->process) {
            $this->logger->notice(sprintf('Stopping Redis (PID %s)', $this->redisPid));
            $this->process->signal(SIGTERM);
            $attempts = 0;
            $timer = EventLoop::repeat(0.1, function () use ($deferred, &$timer, &$attempts) {
                if (! $this->process->isRunning()) {
                    $deferred->complete();
                    EventLoop::cancel($timer);
                    $timer = null;
                    $this->process = null;
                }
                $attempts++;
                if ($attempts > 50) {
                    $this->logger->warning('Redis did not stop in time, killing the process');
                    $this->process->kill();
                    $this->process = null;
                    EventLoop::cancel($timer);
                    $timer = null;
                }
            });

        }
        await([$deferred->getFuture()]);
    }
}
