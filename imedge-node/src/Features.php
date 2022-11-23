<?php

namespace IcingaDataNode;

use DirectoryIterator;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use IcingaDataNode\Network\ConnectionHandler;
use IcingaDataNode\Network\DataNodeConnections;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SplFileInfo;

use function Amp\async;
use function Amp\Future\awaitAll;

class Features
{
    /** @var Feature[] */
    protected array $loaded = [];

    public function __construct(
        protected readonly NodeIdentifier $nodeIdentifier,
        protected readonly ConnectionHandler $connectionHandler,
        protected readonly DataNodeConnections $dataNodeConnections,
        protected readonly Services $services,
        protected readonly Events $events,
        protected string $baseDirectory,
        protected readonly LoggerInterface $logger
    ) {
        FilesystemUtil::requireDirectory($this->getFeatureConfigDirectory());
        FilesystemUtil::requireDirectory($this->getEnabledFeaturesDirectory());
    }

    public function getEnabledFeaturesDirectory(): string
    {
        return $this->baseDirectory . '/features-enabled';
    }

    public function getFeatureConfigDirectory(): string
    {
        return $this->baseDirectory . '/feature';
    }

    public function loadAll(DataNode $node): void
    {
        foreach ($this->enumEnabled() as $name => $directory) {
            $this->load($name, $directory);
        }
        foreach ($this->getLoaded() as $feature) {
            $node->tellSubscribersAboutLoadedFeature($feature);
            $node->applyFeatureEventHandlers($feature);
        }
    }

    public function hasLoaded(string $name): bool
    {
        return isset($this->loaded[$name]);
    }

    public function load(string $name, string $directory): void
    {
        if ($this->hasLoaded($name)) {
            $this->logger->error("Cannot load feature $name twice");
        }
        $this->loaded[$name] = new Feature(
            $name,
            $directory,
            $this->baseDirectory . "/feature/$name.json",
            $this->nodeIdentifier,
            $this->services,
            $this->events,
            $this->logger
        );
        // The feature registered an RPC connection. This allows talking to metric instances
        $this->loaded[$name]->on(
            Feature::ON_CONNECTION,
            function (
                JsonRpcConnection $connection,
                string $peerAddress
            ) {
                $this->connectionHandler->registerConnected($connection, $peerAddress);
                $this->dataNodeConnections->onConnectedPeer(
                    $this->connectionHandler,
                    $connection,
                    $peerAddress
                );
            }
        );
        $this->loaded[$name]->on(Feature::ON_CONNECTION_REMOVED, function (
            string $peerAddress
        ) {
            $this->connectionHandler->removeConnected($peerAddress);
            $this->dataNodeConnections->onDisconnect($peerAddress);
        });
        $this->loaded[$name]->register();
    }

    public function shutdown(): void
    {
        $futures = [];
        foreach ($this->loaded as $feature) {
            $futures[] = async($feature->shutdown(...));
        }

        awaitAll($futures);
    }

    /**
     * @return array<string, Feature>
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }

    public function hasEnabled($name): bool
    {
        return file_exists($this->getEnabledFeaturesDirectory() . "/$name");
    }

    public function enable(string $name, string $sourcePath): void
    {
        if (! preg_match('/^[a-z]{3,16}$/', $name)) {
            throw new InvalidArgumentException("'$name' is not a valid feature name");
        }
        if ($this->hasLoaded($name)) {
            throw new InvalidArgumentException("Feature $name has already been loaded");
        }

        $sourcePath = rtrim($sourcePath, '/');
        if (! file_exists($sourcePath) || ! is_readable($sourcePath)) {
            throw new InvalidArgumentException("There is no readable feature at $sourcePath");
        }
        if ($this->hasEnabled($name)) {
            throw new InvalidArgumentException("Feature $name has already been enabled");
        }

        $featureFile = "$sourcePath/feature.php";
        if (! file_exists($featureFile) || ! is_readable($featureFile)) {
            throw new InvalidArgumentException(sprintf(
                "Path %s exists, but doesn't seem to be an %s feature",
                $sourcePath,
                Defaults::APPLICATION_NAME
            ));
        }
        $link = $this->getEnabledFeaturesDirectory() . "/$name";
        if (!symlink("$sourcePath/", $link)) {
            throw new InvalidArgumentException("Failed to link $sourcePath to $link");
        }
    }

    protected function enumEnabled(): array
    {
        $modules = [];
        $directory = $this->getEnabledFeaturesDirectory();
        if (!is_dir($directory)) {
            return $modules;
        }
        if (!is_readable($directory)) {
            $this->logger->error("$directory is not readable");
        }
        foreach (new DirectoryIterator($directory) as $fileInfo) {
            if ($fileInfo->isLink() && ctype_alnum($fileInfo->getBasename())) {
                $target = new SplFileInfo($fileInfo->getLinkTarget());
                if ($target->isDir() && $target->isReadable()) {
                    $modules[$fileInfo->getBasename()] = $target->getPathname();
                }
            }
        }

        return $modules;
    }
}
