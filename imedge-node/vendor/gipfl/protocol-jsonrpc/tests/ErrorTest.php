<?php

namespace gipfl\Tests\Prototol\JsonRpc;

use gipfl\Protocol\JsonRpc\Response;
use gipfl\Protocol\JsonRpc\TestCase;

class ErrorTest extends TestCase
{
    protected $examples = [
        '{"jsonrpc":"2.0","id":1,"error":'
        . '{"code":-32600,"message":"Expected valid JSON-RPC, got no \'result\' property"}}',
    ];

    public function testParsesSimpleResponseWithPositionalParams()
    {
        $packet = $this->parseExample(0);
        $this->assertInstanceOf(Response::class, $packet);
        $this->assertEquals(-32600, $packet->getError()->getCode());
        $this->assertEquals("Expected valid JSON-RPC, got no 'result' property", $packet->getError()->getMessage());
    }
}
