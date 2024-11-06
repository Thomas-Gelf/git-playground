<?php

namespace IMEdge\Log\Formatter;

interface LogFormatter
{
    public function format(string $level, string $message, array $context = []): string;
}
