<?php

namespace IcingaFeature\Snmp\DataMangler;

interface DataManglerInterface
{
    public function transform(mixed $string): mixed;
}
