<?php

namespace IcingaDataNode\Command;

use GetOpt\GetOpt;
use gipfl\Log\Filter\LogLevelFilter;
use gipfl\Log\Logger;
use gipfl\Log\Writer\JournaldLogger;
use gipfl\Log\Writer\JsonRpcConnectionWriter;
use gipfl\Log\Writer\WritableStreamWriter;
use gipfl\SystemD\systemd;
use IcingaDataNode\Logging\SystemdStdoutWriter;
use React\Stream\WritableResourceStream;

class ProcessLogger
{
    public static function createForOptions(GetOpt $options): Logger
    {
        $logger = new Logger();
        self::applyLogFilters($logger, $options);

        return $logger;
    }

    public static function detectAndApplyLogWriter(Logger $logger, $identifier, GetOpt $options): void
    {
        $command = $options->getCommand();
        if ($command instanceof RpcCommand) {
            $logger->addWriter(new JsonRpcConnectionWriter($command->rpc()));
        } elseif (systemd::startedThisProcess()) {
            if (@file_exists(JournaldLogger::JOURNALD_SOCKET)) {
                $logger->addWriter((new JournaldLogger())->setIdentifier($identifier));
            } else {
                $logger->addWriter(new SystemdStdoutWriter());
            }
        } else {
            $logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDERR)));
        }
    }

    protected static function applyLogFilters(Logger $logger, GetOpt $options): void
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
