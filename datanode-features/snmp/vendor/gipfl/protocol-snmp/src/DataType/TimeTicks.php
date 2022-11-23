<?php

namespace gipfl\Protocol\Snmp\DataType;

class TimeTicks extends Unsigned32
{
    public const TAG = self::TIME_TICKS;
    protected int $tag = self::TAG;
}
