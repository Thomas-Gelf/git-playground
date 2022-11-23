<?php

namespace IcingaDataNode\Monitoring;

use gipfl\Json\JsonSerialization;

class Threshold implements JsonSerialization
{
    const OUTSIDE = 'outside';
    const INSIDE = 'inside';

    protected Range $range;
    protected bool $outsideIsValid;

    public function __construct(Range $range, bool $outsideIsValid = true)
    {
        $this->range = $range;
        $this->outsideIsValid = $outsideIsValid;
    }

    public function valueIsValid($value): bool
    {
        if ($this->outsideIsValid) {
            return ! $this->range->contains($value);
        }

        return $this->range->contains($value);
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'valid' => $this->outsideIsValid ? static::OUTSIDE : static::INSIDE,
            'range' => $this->range,
        ];
    }

    public static function fromSerialization($any): self
    {
        return new static(
            Range::fromSerialization($any->range),
            $any->valid
        );
    }
}
