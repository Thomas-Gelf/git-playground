<?php

namespace IcingaFeature\Snmp\DataStructure;

use gipfl\Protocol\Snmp\DataType\DataType;

interface EnumInterface
{
    public function getLabel(): string;
}
