<?php

namespace IcingaMetrics\Command;

use function ctype_alnum;
use function escapeshellarg;
use function escapeshellcmd;
use function implode;
use function preg_replace;

class ExecCommandString
{
    public static function create(string $binary, array $args = []): string
    {
        return static::getEscapedCommandString($binary, $args);
    }

    protected static function getEscapedCommandString(string $binary, array $arguments): string
    {
        $command = ['exec', escapeshellcmd($binary)];

        foreach ($arguments as $argument) {
            if (ctype_alnum(preg_replace('/^-{1,2}/', '', $argument))) {
                $command[] = $argument;
            } else {
                $command[] = escapeshellarg($argument);
            }
        }

        return implode(' ', $command);
    }
}
