<?php

namespace IMEdge\Metrics;

use OutOfRangeException;

enum PrefixType
{
    case BINARY;  // IEC
    case METRIC; // SI
    protected const BINARY_SYMBOLS = ['', 'Ki', 'Mi', 'Gi', 'Ti', 'Pi', 'Ei', 'Zi', 'Yi'];
    protected const METRIC_SYMBOLS = [
        -4 => 'p',
        -3 => 'n',
        -2 => 'µ',
        -1 => 'm',
        0 => '',
        1 => 'k',
        2 => 'M',
        3 => 'G',
        4 => 'T',
        5 => 'P',
        6 => 'E',
        7 => 'Z',
        8 => 'Y',
    ];

    protected const BINARY_NAMES = ['', 'kibi', 'mebi', 'gibi', 'tebi', 'pebi', 'exbi', 'zebi', 'yobi'];
    protected const METRIC_NAMES = [
        -4 => 'pico',
        -3 => 'nano',
        -2 => 'micro',
        -1 => 'milli',
        0 => '',
        1 => 'kilo',
        2 => 'mega',
        3 => 'giga',
        4 => 'tera',
        5 => 'peta',
        6 => 'exa',
        7 => 'zeta',
        8 => 'yotta'
    ];

    public function getBase(): int
    {
        return match ($this) {
            self::METRIC => 1000,
            self::BINARY => 1024,
        };
    }

    public function getName(int $exponent): string
    {
        return match ($this) {
            self::METRIC => self::METRIC_NAMES[$exponent] ?? throw new OutOfRangeException(
                "Metric name for exponent $exponent is not available"
            ),
            self::BINARY => self::BINARY_NAMES[$exponent] ?? throw new OutOfRangeException(
                "Binary name for exponent $exponent is not available"
            ),
        };
    }

    public function getSymbol(int $exponent): string
    {
        return match ($this) {
            self::METRIC => self::METRIC_SYMBOLS[$exponent] ?? throw new OutOfRangeException(
                "Metric symbol for exponent $exponent is not available"
            ),
            self::BINARY => self::BINARY_SYMBOLS[$exponent] ?? throw new OutOfRangeException(
                "Binary symbol for exponent $exponent is not available"
            ),
        };
    }
}
