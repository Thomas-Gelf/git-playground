<?php

namespace IcingaMetrics\NamingStrategy;

use IcingaDataNode\Monitoring\Measurement;
use IcingaMetrics\CiConfig;
use IcingaMetrics\DsHelper;
use Psr\Log\LoggerInterface;

/**
 * UNUSED
 */
class DefaultNamingStrategy
{
    /*
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function prepareCiConfig(Measurement $measurement, $keyValue): CiConfig
    {
        $dsList = DsHelper::getDataSourcesFor($this->logger, $keyValue);
        $map = array_combine(array_keys($keyValue), $dsList->listNames());
        return CiConfig::create($dsList->listNames(), $map);
    }
    */
}
