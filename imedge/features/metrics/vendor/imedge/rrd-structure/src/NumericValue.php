<?php

namespace IMEdge\RrdStructure;

class NumericValue
{
    public static function parseLocalizedFloat(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $lower = strtolower($value);
        switch ($lower) {
            case 'nan':
                return NAN;
            case '-nan':
                return -NAN;
            case 'inf':
                return INF;
            case '-inf':
                return -INF;
            default:
                return (float) str_replace(',', '.', $value);
        }
    }
}
