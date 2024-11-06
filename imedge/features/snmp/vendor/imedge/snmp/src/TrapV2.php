<?php

namespace IMEdge\Snmp;

class TrapV2 extends Pdu
{
    public function getTag(): int
    {
        return Pdu::TRAP_V2;
    }
}
