<?php

namespace IMEdge\Log\Writer;

interface LogWriter
{
    public function write(string $level, string $message, array $context = []): void;
}
