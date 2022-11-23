<?php

namespace IcingaMetrics;

use Closure;
use Exception;
use gipfl\RrdTool\RrdCached\RrdCachedClient;
use gipfl\RrdTool\RrdCached\RrdCachedCommand;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use RuntimeException;

use function React\Promise\reject;
use function React\Promise\resolve;
use function React\Promise\Timer\sleep;

class MainUpdateHandler
{
    protected RedisPerfDataApi $redisApi;
    protected LoggerInterface $logger;
    protected RrdCachedClient $rrdCached;
    protected string $position;
    protected float $startupTime;
    protected bool $startingUp = true;
    protected bool $shuttingDown = false;
    protected int $backlogLinesAtStartup = 0;
    protected int $backlogBytesAtStartup = 0;
    protected ?TimerInterface $connecting = null;
    protected bool $readingNextBatch = false;

    /**
     * @param RedisPerfDataApi $redisApi
     * @param RrdCachedClient $rrdCached
     * @param LoggerInterface $logger
     */
    public function __construct(RedisPerfDataApi $redisApi, RrdCachedClient $rrdCached, LoggerInterface $logger)
    {
        $this->redisApi = $redisApi;
        $this->rrdCached = $rrdCached;
        $this->logger = $logger;
    }

    public function run()
    {
        $this->startupTime = microtime(true);
        $this->logger->notice("MainUpdateHandler is starting");
        $this->tryToStart();
        $this->connecting = Loop::addPeriodicTimer(3, $this->tryToStart(...));
    }

    public function stop(): PromiseInterface
    {
        $this->shuttingDown = true;

        return resolve(null);
    }

    protected function tryToStart()
    {
        $this->fetchLastPosition()->then(function ($position) {
            Loop::cancelTimer($this->connecting);
            $this->connecting = null;
            $this->scheduleNextFetch();
        })->catch(function (Exception $e) {
            $this->logger->warning('Got no last position, trying again in 3 seconds: ' . $e->getMessage());
        });
    }

    protected function fetchLastPosition(): PromiseInterface
    {
        return $this->redisApi->fetchLastPosition()->then(function ($position) {
            $this->setInitialPosition($position);
            return $position;
        });
    }

    protected function processNextScheduledBatch()
    {
        if ($this->shuttingDown) {
            return;
        }

        $this->readNextBatch()->then(function ($stream) {
            $this->processBulk($stream);
        }, function (Exception $e) {
            Loop::futureTick(function () use ($e) {
                if ($this->shuttingDown) {
                    return;
                }
                $this->logger->error('Reading next batch failed, continuing in 15s: ' . $e->getMessage());
                sleep(15)->then($this->processNextScheduledBatch(...));
            });
        });
    }

    protected function scheduleNextFetch()
    {
        // Should happen in the next iteration
        Loop::get()->futureTick($this->processNextScheduledBatch(...));
    }

    /**
     * @param $stream
     */
    protected function processBulk($stream)
    {
        if (empty($stream)) {
            if ($this->startingUp) {
                $this->logStartupInfo();
                $this->startingUp = false;
            }

            $this->scheduleNextFetch();

            return;
        }

        $bulk = [];

        // $stream looks like this:
        // [0] => [ 'rrd:stream', [ 0 => .. ]
        $stream = $stream[0][1] ?? null;
        if (empty($stream)) {
            if ($this->startingUp) {
                $this->startingUp = false;
            }
            $this->logger->warning('Got an unexpected stream construct, please let us know');

            $this->scheduleNextFetch();
            return;
        }

        foreach ($stream as $entry) {
            $this->position = $entry[0];
            // entry = [ 1537893299864-0, [key, val, .., ..]
            if ($entry[1][0] !== 'update') {
                throw new RuntimeException("Got invalid update from stream: " . json_encode($entry, 1));
            }
            $line = $entry[1][1];
            if ($this->startingUp) {
                $this->backlogLinesAtStartup++;
                $this->backlogBytesAtStartup += strlen($line) + 1;
            }
            $bulk[] = RrdCachedCommand::UPDATE . " $line";
        }
        if (empty($bulk)) {
            $this->logger->notice('Nothing to send with this batch, pretty strange');
            $this->scheduleNextFetch();
            return;
        }

        $this->rrdCached->batch($bulk)
            ->then(function ($result) use ($stream) {
                if ($result !== true) {
                    $this->processErrors($result, $stream);
                }

                return $this->redisApi->setLastPosition($this->position);
            })->otherwise(function (\Throwable $e) {
                $this->logger->error($e->getMessage());
            })->always(function () {
                $this->scheduleNextFetch();
            });
    }

    /**
     * Special treatment for step errors, as there can potentially be LOTS of
     * them
     *
     * @param $result
     * @param $stream
     */
    protected function processErrors($result, $stream)
    {
        $stepError = 0;
        // TODO: $stepErrorFiles = [];
        foreach ($result as $pos => $error) {
            // rrdCached starts counting with 1
            $realPos = $pos - 1;
            $failedLine = $stream[$realPos][1][1] ?? '<unknown line>';

            if (str_contains($error, 'No such file')) {
                $filename = substr($failedLine, 0, strpos($failedLine, ' '));
                $this->logger->debug(sprintf(
                    'RRDCacheD rejected for missing file "%s" (%s): %s',
                    $filename,
                    $failedLine,
                    $error
                ));
                // TODO: $this->redisApi->deferCi()
            } elseif (str_contains($error, 'minimum one second step')) {
                $stepError++;
            } else {
                $this->logger->debug(sprintf(
                    'RRDCacheD rejected "%s": %s',
                    $failedLine,
                    $error
                ));
            }
        }
        if ($stepError > 0) {
            $this->logger->debug(sprintf(
                'RRDCacheD rejected %d outdated updates',
                $stepError
            ));
        }
    }

    protected function setInitialPosition($position): void
    {
        if (empty($position)) {
            $this->logger->info('Got no former position, fetching full stream');
            $this->position = '0';
        } else {
            $this->logger->info("Resuming stream from $position");
            $this->position = $position;
        }
    }

    protected function assertNotReadingNextBatch()
    {
        if ($this->readingNextBatch) {
            // Safety measure
            $this->logger->error('Reading next batch while already reading');
            throw new RuntimeException('Reading next batch while already reading');
        }
    }

    protected function readNextBatch(): PromiseInterface
    {
        $this->assertNotReadingNextBatch();
        $this->readingNextBatch = true;
        $blockMs = $this->startingUp ? '25' : '10000';
        $result = $this->redisApi
            ->fetchBatchFromStream($this->position, 1000, $blockMs)
            ->catch(function (Exception $e) {
                if ($this->shuttingDown) {
                    return;
                }

                $this->logger->notice('Not shutting down, still an error: ' . $e->getMessage());
            });
        $result->finally(function () {
            $this->readingNextBatch = false;
        });

        return $result;
    }

    protected function logStartupInfo()
    {
        if ($this->backlogLinesAtStartup > 0) {
            $duration = microtime(true) - $this->startupTime;
            if ($duration > 1) {
                $duration = sprintf('%.2Fs', $duration);
            } else {
                $duration = sprintf('%.2Fms', $duration * 1000);
            }
            $this->logger->info(sprintf(
                'Queue is now empty, processed %d lines (%s) in %s after starting up',
                $this->backlogLinesAtStartup,
                CompactFormat::bytes($this->backlogBytesAtStartup),
                $duration
            ));
        } else {
            $this->logger->info('Queue was empty at startup');
        }
    }
}
