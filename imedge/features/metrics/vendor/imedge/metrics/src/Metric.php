<?php

namespace IMEdge\Metrics;

use IMEdge\Json\JsonSerialization;

class Metric implements JsonSerialization
{
    // TODO: take care of special (e.g. INF) and large (e.g. 64bit unsigned) values at serialization time
    // Removed: min, max, warningThreshold, criticalThreshold
    public readonly MetricDatatype $type;

    public function __construct(
        public readonly string $label,
        public int|float|null $value, // -INF, INF, NAN -> float
        ?MetricDatatype $type = null,
        public readonly ?string $unit = null
    ) {
        $this->type = $type ?: MetricDatatype::GAUGE;
    }

    public function jsonSerialize(): array
    {
        return [
            $this->label,
            self::prepareNumberForSerialization($this->value),
            $this->type,
            $this->unit,
        ];
    }

    /**
     * TODO: GMP support, this looses data / precision
     * @param int|float|null $value
     * @return float|int|string|null
     */
    protected static function prepareNumberForSerialization(int|float|null $value): float|int|string|null
    {
        if ($value === null) {
            return null;
        }
        if ($value === INF) {
            return 'INF';
        }
        if ($value === -INF) {
            return '-INF';
        }
        if ($value === NAN) {
            return 'NAN';
        }

        if (is_int($value)) {
            return $value;
        }

        return (float) $value;
    }

    protected static function numberFromSerialization(int|float|string|null $value): float|int|null
    {
        if ($value === null) {
            return null;
        }
        if ($value === 'INF') {
            return INF;
        }
        if ($value === '-INF') {
            return -INF;
        }
        if ($value === 'NAN') {
            return NAN;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        return floatval($value);
    }

    public static function fromSerialization($any): self
    {
        $type = isset($any[2]) ? MetricDatatype::from($any[2]) : null;
        return new Metric($any[0], $any[1], $type, $any[3] ?? null);
    }
}
