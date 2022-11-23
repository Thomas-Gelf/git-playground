<?php

namespace gipfl\Tests\Protocol\Snmp;

use gipfl\Protocol\Snmp\SnmpV1Message;

class SnmpGetRequestV1Test extends SnmpGetRequest
{
    protected string $hexPayload = '302902010004067075626c6963a01c02046b1e393d020100020100300e300c06082b060102010105'
        . '000500';
    protected string $expectedVersion = 'v1';
    protected string $expectedMessageClass = SnmpV1Message::class;
}
