<?php

namespace IcingaFeature\Snmp\SnmpScenario\Event;

class IfUpEvent
{
    const NAME = 'IF_UP';

    public function __construct(
        public readonly string $agentKey,
        public readonly int $ifIndex,
    ) {
    }
}
