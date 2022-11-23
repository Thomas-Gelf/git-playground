<?php

namespace IcingaFeature\Snmp\SnmpScenario\Event;

class IfDownEvent
{
    const NAME = 'IF_DOWN';

    public function __construct(
        public readonly string $agentKey,
        public readonly int $ifIndex,
    ) {
    }
}
