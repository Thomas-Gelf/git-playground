<?php

namespace IcingaDataNode;

use Amp\DeferredFuture;
use Amp\Redis\RedisClient;
use Clue\React\Redis\Factory;
use gipfl\CertificateStore\CaStore\CaStoreDirectory;
use gipfl\CertificateStore\CertificationAuthority;
use gipfl\Cli\Process as CliProcess;
use gipfl\Json\JsonString;
use gipfl\LinuxHealth\Memory;
use gipfl\RedisUtils\RedisInfo;
use IcingaDataNode\Daemon\DaemonComponent;
use IcingaDataNode\Inventory\CentralInventory;
use IcingaDataNode\Monitoring\Ci;
use IcingaDataNode\Monitoring\InternalMetricsCollection;
use IcingaDataNode\Monitoring\Measurement;
use IcingaDataNode\Monitoring\Metric;
use IcingaDataNode\Monitoring\MetricDatatype;
use IcingaDataNode\Network\ConnectionHandler;
use IcingaDataNode\Network\DataNodeConnections;
use IcingaDataNode\Redis\RedisRunner;
use IcingaDataNode\Redis\RedisTableSubscriber;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Revolt\EventLoop;
use RuntimeException;

use function Amp\async;
use function Amp\Future\awaitAll;
use function Amp\Redis\createRedisClient;
use function gethostbyaddr;
use function gethostbyname;
use function gethostname;
use function React\Promise\Timer\timeout;

class DataNode implements DaemonComponent
{
    use DirectoryBasedComponent;

    const CONFIG_TYPE = 'Icinga/DataNode';
    const CONFIG_FILE_NAME = 'data-node.json';
    const CONFIG_VERSION = 'v1';
    const SUPPORTED_CONFIG_VERSIONS = [
        self::CONFIG_VERSION,
    ];
    const SOCKET_PATH = '/run/imedge-node';
    const SOCKET_FILE = self::SOCKET_PATH . '/edgenode.sock';

    // const SOCKET_PATH = '/run/icinga-datanode';
    // const SOCKET_FILE = self::SOCKET_PATH . '/datanode.sock';

    const CONFIG_PERSISTED_CONNECTIONS = 'connections';

    const DEFAULT_REDIS_BINARY = '/usr/bin/redis-server';

    protected Features $features;
    protected CertificationAuthority $ca;
    protected DataNodeRemoteApi $remoteApi;
    public readonly DataNodeConnections $dataNodeConnections;
    public readonly ConnectionHandler $connectionHandler;
    public readonly NodeIdentifier $identifier;
    public readonly Events $events;
    public readonly Services $services;

    protected ?RedisRunner $redisRunner = null;
    /** @var RedisTableSubscriber[] */
    protected array $redisTableSubscribers = [];
    protected ?string $redisSocket = null;
    protected DeferredFuture|string $newRedisSocket;
    protected array $newComponents = [];

    protected function construct(): void
    {
        $this->newRedisSocket = new DeferredFuture();
        /*
        EventLoop::queue(function () {
            print_r(RedisInfo::parse($this->getRedisClient()->execute('INFO')));
        });
        */
    }

    public function start(): void
    {
        $this->run(); // Calls initialize() from DirectoryBasedComponent... not so obvious
    }

    protected function initialize(): void
    {
        $this->events = new Events();
        $this->services = new Services($this, $this->logger);
        $this->identifier = new NodeIdentifier(
            $this->getUuid(),
            $this->name,
            gethostbyaddr(gethostbyname(gethostname())) // TODO: Timeout? Error? Async
        );
        EventLoop::queue(function () {
            $internalMetrics = new InternalMetricsCollection($this->identifier, $this->services, $this->logger);
            $internalMetrics->start();
            $this->newComponents['internalMetrics'] = $internalMetrics;
        });
        // // $this->initializeCa();

        $this->dataNodeConnections = new DataNodeConnections($this, $this->logger);
        $this->connectionHandler = new ConnectionHandler($this->dataNodeConnections, $this->logger);
        EventLoop::queue($this->launchRedis(...));
        $this->initializeFeatures();
        $this->initializeRemoteApi();
        $this->connectionHandler->setConfiguredConnections(
            $this->requireConfig()->getArray(self::CONFIG_PERSISTED_CONNECTIONS)
        );
    }

    public function stop(): void
    {
        foreach ($this->redisTableSubscribers as $subscriber) {
            $subscriber->stop();
        }
        $pending = [
            async($this->features->shutdown(...))
        ];
        if ($this->redisRunner) {
            $pending[] = async($this->redisRunner->stop(...));
        }

        awaitAll($pending);
    }

    public function restart(): void
    {
        $this->stop();
        $this->logger->notice('Shutdown completed, restarting myself');
        EventLoop::delay(0.2, function () {
            CliProcess::restart();
        });
    }

    protected function initializeFeatures(): void
    {
        $this->features = new Features(
            $this->identifier,
            $this->connectionHandler,
            $this->dataNodeConnections,
            $this->services,
            $this->events,
            $this->getConfigDir(),
            $this->logger
        );
        EventLoop::queue(function () {
            $this->features->loadAll($this);

            foreach ($this->features->getLoaded() as $feature) {
                $this->tellSubscribersAboutLoadedFeature($feature);
            }
            // TODO: enable one per one, to allow enabling via API
            $this->remoteApi->setFeatures($this->features);
        });
    }

    public function applyFeatureEventHandlers(Feature $feature): void
    {
        $this->logger->notice('DataNode::applyFeatureEventHandlers:' . $feature->name);
        $feature->on(Feature::ON_INVENTORY_REGISTERED, function (CentralInventory $inventory) {
            $this->setCentralInventory($inventory);
        });
    }

    public function tellSubscribersAboutLoadedFeature(Feature $feature): void
    {
        $this->logger->notice('DataNode::tellSubscribersAboutLoadedFeature:' . $feature->name);
        // TODO: unfinished, wrong!!
        foreach ($feature->getRpcRegistrationSubscribers() as $handler) {
            foreach ($this->features->getLoaded() as $loaded) {
                $loaded->on(Feature::ON_INVENTORY_REGISTERED, function (CentralInventory $inventory) {
                    $this->setCentralInventory($inventory);
                });
                foreach ($loaded->getRegisteredRpcNamespaces() as $registered => $nsHandler) {
                    $handler->registerRpcNamespace($registered, $nsHandler);
                }
            }
        }
    }

    public function setCentralInventory(CentralInventory $inventory): void
    {
        $this->logger->notice(sprintf(
            'Got Central Inventory (%s): %s',
            $this->uuid->toString(),
            get_class($inventory))
        );
        foreach ($this->redisTableSubscribers as $subscriber) {
            $subscriber->stop();
        }
        $this->redisTableSubscribers = [];
        $tables = $inventory->loadTableSyncPositions($this->identifier);
        $this->logger->notice('Got tables: ' . count($tables));
        foreach ($tables as $table => $position) {
            $this->logger->notice('LISTENING to table ' . $table);
            $this->redisTableSubscribers[$table] = new RedisTableSubscriber(
                $table,
                $this,
                $this->logger
            );
            $this->redisTableSubscribers[$table]->setStreamPosition($position);
        }
        foreach ($this->redisTableSubscribers as $subscriber) {
            $subscriber->setCentralInventory($inventory);
        }
    }

    public function getFeatures(): Features
    {
        return $this->features;
    }

    protected function initializeCa(): void
    {
        $directory = $this->getConfigDir() . '/CA';
        // FilesystemUtil::requireDirectory($directory, false, 0700);
        $caStore = new CaStoreDirectory($directory);
        $this->ca = new CertificationAuthority(Application::PROCESS_NAME . '::CA', $caStore);
        echo $this->ca->getCertificate()->toPEM();
    }

    protected function initializeRemoteApi(): void
    {
        $this->remoteApi = $api = new DataNodeRemoteApi($this->logger, $this);
        // $api->setFeatures($this->features);
        $api->run(self::SOCKET_FILE);
    }

    protected function launchRedis(): void
    {
        $this->redisRunner = new RedisRunner(
            static::getRedisBinary(),
            $this->getBaseDir() . '/redis',
            $this->logger
        );
        $socket = $this->redisRunner->run();
        $this->logger->notice('Redis is ready and listening on ' . $socket);
        $deferred = $this->newRedisSocket;
        $this->redisSocket = 'redis+unix://' . $socket;
        $this->newRedisSocket = 'unix://' . $socket;
        $deferred->complete($this->newRedisSocket);
    }

    public function getRedisClient(): RedisClient
    {
        if ($this->newRedisSocket instanceof DeferredFuture) {
            $socket = $this->newRedisSocket->getFuture()->await();
        } else {
            $socket = $this->newRedisSocket;
        }

        return createRedisClient($socket);
    }

    public function waitForRedis(): PromiseInterface
    {
        $deferred = new Deferred();
        if ($this->redisSocket) {
            $factory = new Factory();
            return $factory->createClient($this->redisSocket);
        }
        $timer = EventLoop::repeat(0.1, function () use ($deferred, &$timer) {
            if ($this->redisSocket) {
                EventLoop::cancel($timer);
                $factory = new Factory();
                $deferred->resolve($factory->createClient($this->redisSocket));
            }
        });

        return timeout($deferred->promise(), 10);
    }

    protected function generateName() : string
    {
        if ($fqdn = gethostbyaddr(gethostbyname(gethostname()))) {
            return $fqdn;
        }

        throw new RuntimeException('Node name has not been set, FQDN detection failed');
    }

    protected static function getRedisBinary(): string
    {
        return static::DEFAULT_REDIS_BINARY;
    }
}
