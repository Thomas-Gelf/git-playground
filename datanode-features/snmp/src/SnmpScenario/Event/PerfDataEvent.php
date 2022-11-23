<?php

namespace IcingaFeature\Snmp\SnmpScenario\Event;

class PerfDataEvent
{
    const NAME = 'PERF_DATA';

    public function __construct(
        public readonly string $agentKey,
        public readonly string $counterSet,
        public readonly string|int|null $instance,
        public readonly int $timestamp,
        public readonly array $counters,
    ) {
    }
}
