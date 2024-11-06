<?php

namespace IMEdge\Json;

use JsonSerializable;

interface JsonSerialization extends JsonSerializable
{
    /**
     * @param mixed $any
     * @return static
     */
    public static function fromSerialization($any);
}
