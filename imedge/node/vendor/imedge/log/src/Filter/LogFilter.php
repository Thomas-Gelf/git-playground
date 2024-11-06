<?php

namespace IMEdge\Log\Filter;

use Stringable;

interface LogFilter
{
    public function wants(string|Stringable $level, string $message, array $context = []): bool;
}
