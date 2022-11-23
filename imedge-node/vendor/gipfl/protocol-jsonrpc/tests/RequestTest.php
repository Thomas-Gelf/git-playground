<?php

namespace gipfl\Tests\Prototol\JsonRpc;

use gipfl\Protocol\JsonRpc\Request;
use gipfl\Protocol\JsonRpc\TestCase;

class RequestTest extends TestCase
{
    protected $examples = [
        '{"jsonrpc":"2.0","method":"subtract","params":[42,23],"id":1}',
        '{"jsonrpc":"2.0","method":"subtract","params":{"subtrahend":23,"minuend":42},"id":3}',
    ];

    public function testParsesSimpleRequestsWithPositionalParams()
    {
        $packet = $this->parseExample(0);
        $this->assertInstanceOf(Request::class, $packet);
        $this->assertEquals('subtract', $packet->getMethod());
        $this->assertEquals([42, 23], $packet->getParams());
        $this->assertEquals(1, $packet->getId());
    }

    public function testParsesSimpleRequestsWithNamedParams()
    {
        $packet = $this->parseExample(1);
        $this->assertInstanceOf(Request::class, $packet);
        $this->assertEquals('subtract', $packet->getMethod());
        $this->assertEquals((object) [
            'subtrahend' => 23,
            'minuend'    => 42
        ], $packet->getParams());
        $this->assertEquals(3, $packet->getId());
    }

    public function testRendersRequestWithPositionalParams()
    {
        $this->assertEquals(
            $this->examples[0],
            $this->parseExample(0)->toString()
        );
    }

    public function testRendersRequestWithNamedParams()
    {
        $this->assertEquals(
            $this->examples[1],
            $this->parseExample(1)->toString()
        );
    }
}
