<?php

namespace IMEdge\IcingaPerfData;

use IMEdge\Metrics\Range;
use IMEdge\Metrics\Threshold;

use function is_float;
use function strlen;
use function strpos;
use function substr;

class ThresholdParser
{
    public static function parse(float|string $string): Threshold
    {
        if (is_float($string)) {
            // null or 0 without min?
            return new Threshold(new Range(null, $string), false);
        }
        if ($string[0] === '@') {
            $outsideIsValid = false; // TODO: Wording? Or did I flip inside/outside? Test this!
            $string = substr($string, 1);
        } else {
            $outsideIsValid = true;
        }
        $colon = strpos($string, ':');
        if ($colon === false) {
            $start = 0;
            $end = $string;
        } else {
            $start = substr($string, 0, $colon);
            $end = substr($string, $colon + 1);
        }
        if (strlen($end) === 0) {
            $end = null;
        }
        // -INFINITY
        if ($start === '~') {
            $start = null;
        }

        return new Threshold(new Range($start, $end), $outsideIsValid);
    }
}
