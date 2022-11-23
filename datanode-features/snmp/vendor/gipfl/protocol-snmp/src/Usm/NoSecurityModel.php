<?php

namespace gipfl\Protocol\Snmp\Usm;

use gipfl\Protocol\Snmp\Snmpv3SecurityParameters;
use Sop\ASN1\Type\Primitive\OctetString;

class NoSecurityModel implements Snmpv3SecurityParameters
{
    protected static ?string $rawString = null;

    protected static function generateRawString(): string
    {
        return (new OctetString(''))->toDER();
    }

    public function __toString(): string
    {
        return self::$rawString ??= self::generateRawString();
    }
}
