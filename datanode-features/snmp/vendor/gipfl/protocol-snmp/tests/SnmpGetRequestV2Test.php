<?php

namespace gipfl\Tests\Protocol\Snmp;

use gipfl\Protocol\Snmp\SnmpV2Message;

class SnmpGetRequestV2Test extends SnmpGetRequest
{
    protected string $hexPayload = '302902010104067075626c6963a01c02045de867fd020100020100300e300c06082b060102010105'
    . '000500';
    protected string $expectedVersion = 'v2c';
    protected string $expectedMessageClass = SnmpV2Message::class;
}
