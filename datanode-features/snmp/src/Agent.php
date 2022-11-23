<?php

namespace IcingaFeature\Snmp;

// temporary, to be replaced
class Agent
{
    public function __construct(
        public readonly string $ip_address,
        public readonly string $security_name,
    ) {}
}
