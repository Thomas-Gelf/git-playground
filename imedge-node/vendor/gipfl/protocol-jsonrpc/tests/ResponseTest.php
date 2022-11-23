<?php

namespace gipfl\Tests\Prototol\JsonRpc;

use gipfl\Protocol\JsonRpc\Response;
use gipfl\Protocol\JsonRpc\TestCase;

class ResponseTest extends TestCase
{
    protected $examples = [
        '{"jsonrpc":"2.0","id":1,"result":19}',
    ];

    public function testParsesSimpleResponseWithPositionalParams()
    {
        $packet = $this->parseExample(0);
        $this->assertInstanceOf(Response::class, $packet);
        $this->assertEquals(19, $packet->getResult());
        $this->assertEquals(1, $packet->getId());
    }

    public function testRendersResponseWithNamedParams()
    {
        $this->assertEquals(
            $this->examples[0],
            $this->parseExample(0)->toString()
        );
    }
}
