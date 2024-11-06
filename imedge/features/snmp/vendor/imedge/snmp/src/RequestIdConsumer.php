<?php

namespace IMEdge\Snmp;

interface RequestIdConsumer
{
    public function hasId(int $id): bool;
}
