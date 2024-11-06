<?php

namespace IMEdge\Log;

use Psr\Log\LoggerInterface;

class PrefixLogger extends Logger
{
    public function __construct(
        protected string $prefix,
        protected LoggerInterface $wrappedLogger
    ) {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->wrappedLogger->log($level, $this->prefix . $message, $context);
    }
}
