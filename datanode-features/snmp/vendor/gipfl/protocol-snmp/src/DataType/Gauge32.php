<?php

namespace gipfl\Protocol\Snmp\DataType;

class Gauge32 extends Unsigned32
{
    public const TAG = DataType::GAUGE_32;
    protected int $tag = self::TAG;
}
