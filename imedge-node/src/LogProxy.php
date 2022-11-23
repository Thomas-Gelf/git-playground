<?php

namespace IcingaDataNode;

use Psr\Log\LoggerInterface;

class LogProxy
{
    protected LoggerInterface $logger;

    protected ?string $prefix = null;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setPrefix(?string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function logNotification(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $this->prefix . $message, $context);
    }
}
