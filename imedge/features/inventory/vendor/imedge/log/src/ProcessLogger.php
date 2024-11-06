<?php

namespace IMEdge\Log;

use Amp\ByteStream\WritableResourceStream;
use GetOpt\GetOpt;
use IMEdge\Log\Filter\LogLevelFilter;
use IMEdge\Log\Writer\AmpStreamWriter;
use IMEdge\Log\Writer\JournaldLogger;
use IMEdge\Log\Writer\SystemdStdoutWriter;
use IMEdge\systemd\systemd;

class ProcessLogger
{
    public static function create(string $identifier, ?GetOpt $options = null): Logger
    {
        $logger = new Logger();
        self::applyWriter($logger, $identifier);
        if ($options) {
            self::applyLogFilters($logger, $options);
        }

        return $logger;
    }

    protected static function applyWriter(Logger &$logger, string $identifier): void
    {
        // TODO: JsonRpcConnectionWriter if $options->getCommand() instanceof RpcCommandInterface?
        if (systemd::startedThisProcess()) {
            if (@file_exists(JournaldLogger::JOURNALD_SOCKET)) {
                $writer = new JournaldLogger();
                $writer->setIdentifier($identifier);
                $logger->addWriter($writer);
            } else {
                $logger->addWriter(new SystemdStdoutWriter());
                $logger = new PrefixLogger($identifier, $logger);
            }
        } else {
            $logger->addWriter(new AmpStreamWriter(new WritableResourceStream(STDERR)));
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
