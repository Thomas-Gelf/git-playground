<?php

namespace IcingaFeature\Snmp;

use gipfl\Json\JsonSerialization;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

class Uuid extends RamseyUuid implements JsonSerialization
{
    public static function fromSerialization($any): UuidInterface|static
    {
        return static::fromString($any);
    }
}
