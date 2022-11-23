<?php

namespace IcingaMetrics\Command;

use GetOpt\GetOpt;
use gipfl\Log\Filter\LogLevelFilter;
use gipfl\Log\Logger;

class SubProcessLogger
{
    public static function createForOptions(GetOpt $options): Logger
    {
        $logger = new Logger();
        self::applyLogFilters($logger, $options);

        return $logger;
    }

    protected static function applyLogFilters(Logger $logger, GetOpt $options)
    {
        if (! $options->getOption('debug')->getValue()) {
            if ($options->getOption('verbose')->getValue()) {
                $logger->addFilter(new LogLevelFilter('info'));
            } else {
                $logger->addFilter(new LogLevelFilter('notice'));
            }
        }
    }
}
