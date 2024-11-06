<?php

namespace IMEdge\RrdGraphInfo;

use IMEdge\Json\JsonSerialization;

class GraphInfoValue implements JsonSerialization
{
    /** @var int|float */
    public $min;

    /** @var int|float */
    public $max;

    /**
     * @param int|float $min
     * @param int|float $max
     */
    public function __construct($min, $max)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'min' => $this->min,
            'max' => $this->max,
        ];
    }

    /**
     * @param object{min: int, max: int} $any
     * @return GraphInfoValue
     */
    public static function fromSerialization($any): GraphInfoValue
    {
        return new GraphInfoValue($any->min, $any->max);
    }
}
