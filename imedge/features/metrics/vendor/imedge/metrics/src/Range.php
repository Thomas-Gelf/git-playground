<?php

namespace IMEdge\Metrics;

use IMEdge\Json\JsonSerialization;
use InvalidArgumentException;

/**
 * @deprecated It's not deprecated, but should be re-checked, before being used
 */
class Range implements JsonSerialization
{
    /** @var  */
    protected int|float|null $start = 0;

    /** @var int|float|null */
    protected mixed $end;

    protected bool $startIsInclusive = true;
    protected bool $endIsInclusive = true;

    public function __construct(
        $start,
        $end,
        $startIsInclusive = true,
        $endIsInclusive = true
    ) {
        if ($start !== null) {
            $start = NumberHelper::wantNumber($start);
        }
        if ($end !== null) {
            $end = NumberHelper::wantNumber($end);
        }
        if ($start !== null && $end !== null && $start > $end) {
            throw new InvalidArgumentException(
                "Range start cannot be greater then end, got $start > $end"
            );
        }
        $this->start = $start;
        $this->end = $end;
        $this->startIsInclusive = $startIsInclusive;
        $this->endIsInclusive = $endIsInclusive;
    }

    public function contains($value): bool
    {
        if ($this->end !== null) {
            if ($this->endIsInclusive) {
                if ($value > $this->end) {
                    return false;
                }
            } elseif ($value >= $this->end) {
                return false;
            }
        }
        if ($this->start !== null) {
            if ($this->startIsInclusive) {
                if ($value < $this->start) {
                    return false;
                }
            } elseif ($value <= $this->start) {
                return false;
            }
        }

        return true;
    }

    public function toString()
    {
        // TODO.
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'start' => $this->start,
            'end'   => $this->end,
            'startIsInclusive' => $this->startIsInclusive,
            'endIsInclusive'   => $this->endIsInclusive,
        ];
    }

    public static function fromSerialization($any): self
    {
        return new static(
            $any->start,
            $any->end,
            $any->startIsInclusive,
            $any->endIsInclusive
        );
    }
}
