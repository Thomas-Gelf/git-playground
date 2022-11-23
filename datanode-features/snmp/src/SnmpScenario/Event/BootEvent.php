<?php

namespace IcingaFeature\Snmp\SnmpScenario\Event;

class BootEvent
{
    const NAME = 'BOOT';

    public function __construct(
        public readonly string $agentKey,
    ) {
    }
}
