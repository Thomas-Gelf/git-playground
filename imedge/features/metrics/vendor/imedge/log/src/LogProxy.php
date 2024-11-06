<?php

namespace IMEdge\Log;

use Psr\Log\LoggerInterface;

class LogProxy
{
    public function __construct(
        protected LoggerInterface $logger,
        protected string $prefix = '',
    ) {
    }

    /**
     * Hint: do not remove type-hints, unless JsonRpc PacketHandler supports native type definitions
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function logNotification(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $this->prefix . $message, $context);
    }
}
