<?php

namespace IcingaFeature\Snmp\SnmpScenario;

use IcingaFeature\Snmp\Result;
use JsonSerializable;

class KnownTargetsHealth implements JsonSerializable
{
    protected array $targets = [];

    public function setCurrentResult(string $target, Result $result): void
    {
        $this->targets[$target] = $result;
    }

    public function forget(string $target): void
    {
        unset($this->targets[$target]);
    }

    public function jsonSerialize(): array
    {
        return $this->targets;
    }
}
