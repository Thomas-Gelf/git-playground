<?php

namespace IMEdge\Metrics;

use InvalidArgumentException;

use function is_float;
use function is_int;
use function is_numeric;
use function preg_match;

/**
* @deprecated It's not deprecated, but should be re-checked, before being used -> it misses GMP support
*/
class NumberHelper
{
    public static function wantNumber(string|float|int $any): float|int
    {
        if (is_int($any) || is_float($any)) {
            return $any;
        }

        return static::parseNumber($any);
    }

    public static function parseNumber(string $string): float|int
    {
        if (! is_numeric($string)) {
            throw new InvalidArgumentException(
                "Numeric value expected, got $string"
            );
        }
        if (preg_match('/^-?\d+$/', $string)) {
            return (int) $string;
        }

        return (float) $string;
    }
}
