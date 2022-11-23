<?php

namespace gipfl\Tests\Protocol\Snmp;

use gipfl\Protocol\Snmp\GetRequest;
use gipfl\Protocol\Snmp\SnmpMessage;
use gipfl\Protocol\Snmp\SnmpV1Message;
use gipfl\Protocol\Snmp\SnmpV2Message;
use PHPUnit\Framework\TestCase;

abstract class SnmpGetRequest extends TestCase
{
    protected string $hexPayload = '';
    protected string $expectedVersion = 'none';
    /** @var class-string */
    protected string $expectedMessageClass = TestCase::class;

    public function testParsesGetRequest(): void
    {
        $request = $this->getSysName();
        $this->assertInstanceOf($this->expectedMessageClass, $request);
        $this->assertInstanceOf(GetRequest::class, $request->getPdu());
        $this->assertEquals($this->expectedVersion, $request->getVersion());
    }

    public function testParsesCommunity(): void
    {
        /** @var SnmpV2Message|SnmpV1Message $request */
        $request = $this->getSysName();
        $this->assertEquals('public', $request->community);
    }

    protected function getSysName(): SnmpMessage
    {
        $hexStream = $this->hexPayload;
        return $this->parse($hexStream);
    }

    protected static function parse(string $string): SnmpMessage
    {
        $string = hex2bin($string);
        assert(is_string($string));
        return SnmpMessage::fromBinary($string);
    }
}
