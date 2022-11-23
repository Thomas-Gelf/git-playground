<?php

namespace IcingaMetrics;

use gipfl\DataType\Settings;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use React\Promise\PromiseInterface;
use stdClass;
use function React\Promise\reject;

class RpcNamespaceMetrics
{
    public function __construct(
        protected FeatureRunner $runner,
        protected readonly LoggerInterface $logger
    ) {}

    /**
     * Create a new Metrics Store
     *
     * This will create a Metric Store directory structure in the given directory,
     * configure and start a dedicated daemon with related helper daemons (Redis,
     * RRDcached) and begin accepting Metrics and shipping Graphs
     *
     * @param string $name
     * @param string $baseDir
     * @return Settings
     */
    public function createStoreRequest(string $name, string $baseDir): Settings
    {
        $store = new MetricStore($baseDir, $this->logger);
        $store->setName($name);
        $this->runner->claimMetricStore($store);
        return $store->requireConfig();
    }

    /**
     * @param string $uuid
     * @return PromiseInterface
     */
    public function deleteStoreRequest(string $uuid): PromiseInterface
    {
        return reject(new \Exception('Not yet'));
    }

    public function getStoresRequest(): stdClass
    {
        $result = [];
        foreach ($this->runner->getMetricStores() as $store) {
            $uuid = $store->getUuid()->toString();
            $result[$uuid] = (object) [
                'name' => $store->getName(),
                'uuid' => $uuid,
                'path' => $store->getBaseDir(),
            ];
        }

        return (object) $result;
    }

    /**
     * @param string $uuid
     * @return Settings
     */
    public function getMetricStoreSettingsRequest(string $uuid): Settings
    {
        $uuid = Uuid::fromString($uuid);
        foreach ($this->runner->getMetricStores() as $store) {
            if ($store->getUuid()->equals($uuid)) {
                return $store->requireConfig();
            }
        }

        throw new InvalidArgumentException('Found no MetricStore with UUID=' . $uuid->toString());
    }
}
