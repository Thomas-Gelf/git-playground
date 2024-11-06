<?php

namespace IMEdge\IcingaPerfData;

use IMEdge\Metrics\Metric;
use IMEdge\Metrics\MetricDatatype;
use InvalidArgumentException;

use function is_float;
use function is_int;
use function is_numeric;
use function preg_match;
use function strlen;

class PerfDataStringParser
{
    public static function parseValueString(string $valueString, string $label): Metric
    {
        $parts = str_getcsv($valueString, ';', "'");
        $metric = static::createDatePointForValue($label, $parts[0] ?? '');

        // TODO:
        /*
        if (isset($parts[1]) && strlen($parts[1] ?? '') > 0) {
            $metric->setWarningThreshold(ThresholdParser::parse($parts[1]));
        }
        if (isset($parts[2]) && strlen($parts[2]) > 0) {
            $metric->setCriticalThreshold(ThresholdParser::parse($parts[2]));
        }
        if (isset($parts[3]) && strlen($parts[3]) > 0) {
            $metric->setMin(static::wantNumber($parts[3]));
        }
        if (isset($parts[4]) && strlen($parts[4]) > 0) {
            $metric->setMax(static::wantNumber($parts[4]));
        }
        */

        return $metric;
    }

    protected static function wantNumber(float|int|string $any): float|int
    {
        if (is_int($any) || is_float($any)) {
            return $any;
        }

        return static::parseNumber($any);
    }

    protected static function parseNumber(string $string): float|int
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

    protected static function createDatePointForValue(string $label, string $string): Metric
    {
        if (preg_match('/^(-?\d+(?:\.\d+)?(?:[eE]\+?\d{1,3})?)([a-zA-Z%°]+)?$/u', $string, $v)) {
            if (isset($v[2])) {
                if ($v[2] === 'c') {
                    $unit = null;
                    $counter = true;
                } else {
                    $unit = $v[2];
                    $counter = false;
                }
            } else {
                $unit = null;
                $counter = false;
            }

            return new Metric($label, static::wantNumber($v[1]), $counter ? MetricDatatype::COUNTER : null, $unit);
        }

        throw new InvalidArgumentException("'$string' is no a valid PerfData value");
    }
}
