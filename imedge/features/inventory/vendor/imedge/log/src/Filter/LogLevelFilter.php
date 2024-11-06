<?php

namespace IMEdge\Log\Filter;

use IMEdge\Log\LogLevel;
use Stringable;

class LogLevelFilter implements LogFilter
{
    protected int $level;

    public function __construct(string $level)
    {
        $this->level = LogLevel::mapNameToNumeric($level);
    }

    public function wants(string|Stringable $level, string $message, array $context = []): bool
    {
        return LogLevel::mapNameToNumeric($level) <= $this->level;
    }

    public function getLevel(): string
    {
        return LogLevel::mapNumericToName($this->level);
    }
}
