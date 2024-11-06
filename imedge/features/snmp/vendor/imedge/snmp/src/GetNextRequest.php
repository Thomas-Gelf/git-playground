<?php

namespace IMEdge\Snmp;

class GetNextRequest extends Pdu
{
    protected bool $wantsResponse = true;

    public function getTag(): int
    {
        return Pdu::GET_NEXT_REQUEST;
    }
}
