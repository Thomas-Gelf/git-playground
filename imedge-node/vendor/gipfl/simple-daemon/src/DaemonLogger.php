<?php

namespace gipfl\SimpleDaemon;

use GetOpt\GetOpt;
use GetOpt\Option;
use gipfl\Log\Filter\LogLevelFilter;
use gipfl\Log\Logger;
use gipfl\Log\Writer\JournaldLogger;
use gipfl\Log\Writer\SystemdStdoutWriter;
use gipfl\Log\Writer\WritableStreamWriter;
use gipfl\SystemD\systemd;
use React\EventLoop\Loop;
use React\Stream\WritableResourceStream;

class DaemonLogger
{
    public static function addLoggingOptionsTo(GetOpt $options): void
    {
        $options->addOptions([
            Option::create('v', 'verbose')->setDescription('Enable verbose logging'),
            Option::create('d', 'debug')->setDescription('Enable debug logging')
        ]);
    }

    public static function create(GetOpt $options): Logger
    {
        $logger = new Logger();
        self::applyLogFilters($logger, $options);

        return $logger;
    }

    public static function daemon(string $identifier, GetOpt $options): Logger
    {
        static::addLoggingOptionsTo($options);
        $logger = static::create($options);
        static::detectAndApplyLogWriter($logger, $identifier);

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
        $options->process();
        if (! $options->getOption('debug')) {
            if ($options->getOption('verbose')) {
                $logger->addFilter(new LogLevelFilter('info'));
            } else {
                $logger->addFilter(new LogLevelFilter('notice'));
            }
        }
    }
}
