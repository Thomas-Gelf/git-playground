<?php

namespace IcingaFeature\Snmp\Scenario;

use Attribute;

#[Attribute]
class PollingTask
{
    public function __construct(
        public readonly string $name,
        public readonly int $defaultInterval,
    ) {}
}
