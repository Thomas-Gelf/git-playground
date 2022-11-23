<?php

namespace IcingaFeature\Snmp;

class RequestedOidList
{
    public function __construct(
        public readonly array $oidList
    ) {
    }
}
