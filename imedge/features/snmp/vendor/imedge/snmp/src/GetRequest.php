<?php

namespace IMEdge\Snmp;

class GetRequest extends Pdu
{
    protected bool $wantsResponse = true;

    public function getTag(): int
    {
        return Pdu::GET_REQUEST;
    }
}
