<?php

namespace gipfl\Tests\Prototol\JsonRpc\Handler;

use gipfl\Protocol\JsonRpc\Handler\ContextMetadataParser;
use gipfl\Protocol\JsonRpc\Handler\RpcUserInfo;
use gipfl\Protocol\JsonRpc\TestCase;

class MetaDataTest extends TestCase
{
    public function testCorrectlyParsesRpcContextMetadata()
    {
        require_once __DIR__ . '/SampleMetaData.php';
        $context = new SampleMetaData(new RpcUserInfo());
        $meta = ContextMetadataParser::analyzeRcpContext($context);
        $this->assertEquals('sample', $meta->namespace);

        $method = $meta->getMethod('test');
        $this->assertEquals('test', $method->getName());
        $this->assertEquals('request', $method->getRequestType());
        $this->assertEquals('void', $method->getResultType());

        $int = $method->getParameter('someNumber');
        $this->assertEquals('someNumber', $int->getName());
        $this->assertEquals('int', $int->getType());
        $this->assertEquals('A random number', $int->getDescription());

        $string = $method->getParameter('andSomeString');
        $this->assertEquals('andSomeString', $string->getName());
        $this->assertEquals('string', $string->getType());
        $this->assertEquals('And a textual parameter', $string->getDescription());

        $method = $meta->getMethod('someTest');
        $this->assertEquals('someTest', $method->getName());
        $this->assertEquals('notification', $method->getRequestType());
        $this->assertEquals('void', $method->getResultType());

        $bool = $method->getParameter('aBoolean');
        $this->assertEquals('aBoolean', $bool->getName());
        $this->assertEquals('boolean', $bool->getType());
        $this->assertEquals(
            "This parameter is a boolean value with a multiline description. Let's see how this behaves",
            $bool->getDescription()
        );
    }
}
