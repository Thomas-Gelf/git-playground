<?php

namespace IMEdge\RrdStructure;

use InvalidArgumentException;

use function count;
use function implode;
use function in_array;

class RraAggregation extends Rra
{
    /**
     * @var string[]
     */
    protected static array $functions = [
        'AVERAGE',
        'MIN',
        'MAX',
        'LAST'
    ];

    /**
     * What percentage of UNKOWN data is allowed so that the consolidated value is
     * still regarded as known: 0% - 99% (0-1). Typical is 50% (0.5).
     */
    protected float $xFilesFactor;

    protected int $steps;

    public static function isKnown(string $name): bool
    {
        return in_array($name, self::$functions);
    }

    /**
     * @param string $str xff:steps:rows
     */
    public function setArgumentsFromString(string $str): void
    {
        $parts = explode(':', $str);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException(
                "Expected 'xff:steps:rows' RRA aggregation function arguments, got '$str''"
            );
        }

        $this->xFilesFactor = (float) $parts[0];
        $this->steps = (int) $parts[1];
        $this->rows = (int) $parts[2];
    }

    /**
     * @param array{'xff': float, 'pdp_per_row': int, 'rows': int}$info
     * @return void
     */
    public function setArgumentsFromInfo(array $info): void
    {
        $this->xFilesFactor = $info['xff'];
        $this->steps = $info['pdp_per_row'];
        $this->rows = $info['rows'];
    }

    public function getSteps(): int
    {
        return $this->steps;
    }

    public function getXFilesFactor(): ?float
    {
        return $this->xFilesFactor;
    }

    public function getDataSize(): int
    {
        return (int) ($this->rows * static::BYTES_PER_DATAPOINT);
    }

    public function toString(): string
    {
        return 'RRA:' . implode(':', [
            $this->consolidationFunction,
            $this->xFilesFactor,
            $this->steps,
            $this->rows
        ]);
    }
}
