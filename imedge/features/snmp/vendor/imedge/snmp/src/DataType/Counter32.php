<?php

namespace IMEdge\Snmp\DataType;

class Counter32 extends Unsigned32
{
    public const TAG = DataType::COUNTER_32;
    protected int $tag = self::TAG;
}
