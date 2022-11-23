<?php

namespace IcingaFeature\Snmp\SnmpScenario\Event;

class DbUpdateEvent
{
    const NAME = 'DB_UPDATE';

    /**
     * @param array<string, int|string|float> $properties
     */
    public function __construct(
        public readonly string $agentKey,
        public readonly string|int|null $instanceKey,
        public readonly string $table,
        public readonly array $properties,
    ) {
    }
}
