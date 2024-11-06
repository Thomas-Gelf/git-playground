<?php

namespace IMEdge\Metrics;

use function abs;
use function floor;
use function log;
use function sprintf;

class Format
{
    public static function decimal(int|float $value): string
    {
        return self::compact($value, PrefixType::METRIC);
    }

    public static function bits(int|float $value, PrefixType $prefixType = PrefixType::METRIC): string
    {
        return self::compact($value, $prefixType) . 'bit';
    }

    public static function bitsPerSecond(int|float $value, PrefixType $prefixType = PrefixType::METRIC): string
    {
        return self::compact($value, $prefixType) . 'bit/s';
    }

    public static function bytes(int|float $value, PrefixType $prefixType = PrefixType::BINARY): string
    {
        return self::compact($value, $prefixType) . 'B';
    }

    public static function hz(int|float $value, PrefixType $prefixType = PrefixType::BINARY): string
    {
        return self::compact($value, $prefixType) . 'hz';
    }

    public static function compact(int|float $value, PrefixType $prefixType): string
    {
        $base = $prefixType->getBase();
        if ($value === 0 || $value === 0.0) {
            return sprintf('%.3G %s', 0, $prefixType->getSymbol(0));
        }

        if ($value < 0) {
            $value = abs($value);
            $sign = '-';
        } else {
            $sign = '';
        }

        $exponent = (int) floor(log($value, $base));
        $result = $value / ($base ** $exponent);
        if ($exponent < 0) {
            if ($prefixType === PrefixType::BINARY) {
                $result = 0;
                $exponent = 0;
            } elseif (round($result) >= $base) {
                $result /= $base;
                $exponent--;
            }
        } elseif (round($result) >= $base) {
            $result /= $base;
            $exponent++;
        }

        return sprintf('%s%.3G %s', $sign, $result, $prefixType->getSymbol($exponent));
    }
}
