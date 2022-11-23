<?php

namespace IcingaMetrics\Command;

use GetOpt\GetOpt;
use gipfl\Log\Filter\LogLevelFilter;
use gipfl\Log\Logger;
use gipfl\Log\Writer\JournaldLogger;
use gipfl\Log\Writer\SystemdStdoutWriter;
use gipfl\Log\Writer\WritableStreamWriter;
use gipfl\SystemD\systemd;
use React\EventLoop\Loop;
use React\Stream\WritableResourceStream;

class ProcessLogger
{
    public static function createForOptions(GetOpt $options): Logger
    {
        $logger = new Logger();
        self::applyLogFilters($logger, $options);

        return $logger;
    }

    public static function detectAndApplyLogWriter(Logger $logger, $identifier)
    {
        if (systemd::startedThisProcess()) {
            if (@file_exists(JournaldLogger::JOURNALD_SOCKET)) {
                $logger->addWriter((new JournaldLogger())->setIdentifier($identifier));
            } else {
                $logger->addWriter(new SystemdStdoutWriter(Loop::get()));
            }
        } else {
            $logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDERR, Loop::get())));
        }
    }

    protected static function applyLogFilters(Logger $logger, GetOpt $options)
    {
        if (! $options->getOption('debug')) {
            if ($options->getOption('verbose')) {
                $logger->addFilter(new LogLevelFilter('info'));
            } else {
                $logger->addFilter(new LogLevelFilter('notice'));
            }
        }
    }
}
