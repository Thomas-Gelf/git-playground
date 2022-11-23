<?php

namespace IcingaMetrics;

use gipfl\RrdTool\AsyncRrdtool;
use gipfl\RrdTool\DsList;
use gipfl\RrdTool\RrdCached\RrdCachedClient;
use gipfl\RrdTool\RrdGraphInfo;
use gipfl\RrdTool\RrdInfo;
use gipfl\RrdTool\RrdSummary;
use InvalidArgumentException;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\resolve;

class RpcNamespaceRrd
{
    protected AsyncRrdtool $rrdtool;
    protected RrdCachedClient $client;

    public function __construct(AsyncRrdtool $rrdtool, RrdCachedClient $client)
    {
        $this->rrdtool = $rrdtool;
        $this->client = $client;
    }

    public function versionRequest(): string
    {
        return 'v0.8.0';
    }

    /**
     * @param string $format
     * @param string $command
     * @return PromiseInterface
     */
    public function graphRequest(string $format, string $command): PromiseInterface
    {
        $rrdtool = $this->rrdtool;
        $start = microtime(true);
        /*
        $binary = '/usr/bin/rrdtool';
        $basedir = '/shared/containers/web/rrd';
        $rrdtool = new Rrdtool("$basedir/data", $binary, "$basedir/rrdcached.sock");
        */
        // Used to be $this->graph->getFormat()

// Logger::info(date('Ymd His'.substr((string)microtime(), 1, 8).' e') . 'Running ' . $command);
        return $rrdtool->send($command)->then(function ($image) use ($start, $format) {
            $duration = \microtime(true) - $start;
            if (\strlen($image) === 0) {
                throw new \RuntimeException('Got an empty STDOUT');
            }
            // $this->logger->info('Got STDOUT: ' . \strlen($image) . 'Bytes');
            $info = RrdGraphInfo::parseRawImage($image);
            $info['time_spent'] = $duration;
            $imageString = \substr($image, $info['headerLength']);
            $props = $info;
            RrdGraphInfo::appendImageToProps($props, $imageString, $format);
            return $props;
        });

        // $info['rrdtool_total_time'] = $rrdtool->getTotalSpentTimings();
        // $info['print'] = $graph->translatePrintLabels($info['print']);
// Logger::info(date('Ymd His'.substr((string)microtime(), 1, 8).' e') . 'Result ready');
    }

    /**
     * @param string $file
     * @return PromiseInterface
     */
    public function recreateRequest(string $file): PromiseInterface
    {
        return $this->rrdtool->recreateFile($file, true);
    }

    /**
     * @param string $file
     * @param string $tuning
     * @return \React\Promise\Promise <string>
     */
    public function tuneRequest(string $file, string $tuning): PromiseInterface
    {
        $rrdtool = $this->rrdtool;

        // string(25) "OK u:0,24 s:0,00 r:31,71 " ??
        return $rrdtool->send("tune $file $tuning");
    }

    public function mergeRequest(array $files, string $outputFile)
    {
        $jobs = [];
        if (empty($files)) {
            throw new InvalidArgumentException('Merging requires at least one file');
        }
        $cmdSuffix = '';
        foreach ($files as $file) {
            $jobs[$file] = $this->client->info($file);
            $cmdSuffix .= " --source $file";
        }
        return all($jobs)->then(function ($infos) use ($outputFile, $cmdSuffix) {
            /** @var RrdInfo[] $infos */
            $first = current($infos);
            $newDs = new DsList();
            foreach ($infos as $info) {
                foreach ($info->getDsList()->getDataSources() as $ds) {
                    if (! $newDs->hasName($ds->getName())) {
                        $newDs->add($ds);
                    }
                }
            }
            $rra = $first->getRraSet();
            return $this->rrdtool->send("create $outputFile $rra $newDs$cmdSuffix");
        });
    }

    /**
     * @param string $file
     * @return PromiseInterface
     */
    public function flushRequest(string $file): PromiseInterface
    {
        return $this->client->flush($file);
    }

    /**
     * @param string $file
     * @return \React\Promise\Promise
     */
    public function forgetRequest(string $file): PromiseInterface
    {
        return $this->client->flush($file);
    }

    /**
     * @param string $file
     * @return PromiseInterface
     */
    public function flushAndForgetRequest(string $file): PromiseInterface
    {
        return $this->client->flushAndForget($file);
    }

    /**
     * @param string $file
     * @return PromiseInterface
     */
    public function deleteRequest(string $file): PromiseInterface
    {
        return $this->client->forget($file)
            ->then(function () use ($file) {
                // This blocks:
                @unlink($this->rrdtool->getBasedir() . '/' . $file);
                return true;
            });
    }

    /**
     * @param string $file
     * @return PromiseInterface
     */
    public function infoRequest(string $file): PromiseInterface
    {
        return $this->client->info($file);
    }

    /**
     * @param string $file
     * @return PromiseInterface
     */
    public function rawinfoRequest(string $file): PromiseInterface
    {
        return $this->client->rawInfo($file);
    }

    /**
     * @param string $file
     * @return PromiseInterface
     */
    public function pendingRequest(string $file): PromiseInterface
    {
        return $this->client->pending($file);
    }

    /**
     * @param string $file
     * @param int $rra
     * @return PromiseInterface
     */
    public function firstRequest(string $file, int $rra = 0): PromiseInterface
    {
        return $this->client->first($file, $rra);
    }

    /**
     * @param string $file
     * @return PromiseInterface
     */
    public function lastRequest(string $file): PromiseInterface
    {
        return $this->client->last($file);
    }

    /**
     * @param array $files
     * @return PromiseInterface
     */
    public function multiLastRequest(array $files): PromiseInterface
    {
        $result = [];
        foreach ($files as $file) {
            $result[$file] = $this->client->last($file);
        }

        return all($result);
    }

    /**
     * @param array $files
     * @param array $dsNames
     * @param int $start
     * @param int $end
     * @return PromiseInterface
     * @throws \Exception
     */
    public function calculateRequest(array $files, array $dsNames, int $start, int $end): PromiseInterface
    {
        $ds = [];
        foreach ($files as $file) {
            foreach ($dsNames as $name) {
                $ds[] = (object)[
                    'filename'   => $file,
                    'datasource' => $name,
                ];
            }
        }

        $summary = new RrdSummary($this->rrdtool);

        return $summary->summariesForDatasources($ds, $start, $end)->then(function ($result) {
            return $result;
        });
    }

    /**
     * @return PromiseInterface
     */
    public function listCommandsRequest() : PromiseInterface
    {
        return $this->client->listAvailableCommands();
    }

    /**
     * @param string $command
     * @return PromiseInterface
     */
    public function hasCommandsRequest(string $command) : PromiseInterface
    {
        return $this->client->hasCommand($command);
    }

    /**
     * @return PromiseInterface
     */
    public function listRecursiveRequest() : PromiseInterface
    {
        return $this->client->hasCommand('LIST')->then(function ($hasList) {
            if ($hasList) {
                return $this->client->listRecursive();
            }
            $basedir = $this->rrdtool->getBasedir();
            $prefixLen = \strlen($basedir) + 1;
            return resolve(\array_map(static function ($file) use ($prefixLen) {
                return \substr($file, $prefixLen);
            }, \glob($basedir . '/**/*.rrd')));
        });
    }
}
