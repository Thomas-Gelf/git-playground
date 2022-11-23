<?php

namespace IcingaFeature\Snmp;

use gipfl\Protocol\Snmp\DataType\DataType;
use IcingaFeature\Snmp\SnmpScenario\SnmpTarget;

class Result
{
    /**
     * @param ?DataType[] $result
     */
    public function __construct(
        public readonly string $requestMethod,
        public readonly SnmpTarget $target,
        public ?array $result = null,
        public ?string $error = null
    ) {
    }

    public function succeeded(): bool
    {
        return $this->result !== null;
    }
}
