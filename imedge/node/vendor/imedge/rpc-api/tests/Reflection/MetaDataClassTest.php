<?php

namespace IMEdge\Tests\RpcApi\Reflection;

use IMEdge\RpcApi\Reflection\MetaDataClass;
use IMEdge\RpcApi\Reflection\MetaDataMethod;
use IMEdge\Tests\RpcApi\Samples\SampleApi;
use PHPUnit\Framework\TestCase;

class MetaDataClassTest extends TestCase
{
    public function testAnApiImplementationCanBeParsed(): void
    {
        $class = MetaDataClass::analyze(SampleApi::class);
        assert($class instanceof MetaDataClass);
        $this->assertEquals('sample', $class->namespace);
        $method = $class->getMethod('some');
        $this->assertInstanceOf(MetaDataMethod::class, $method);
        $this->assertEquals('This method does something', $method->title);
        $this->assertEquals(
            'And there is a description, with a lot of details. It covers multiple lines, and there are some paragraphs'
            . "\n\n"
            . 'Lorem ipsum, next paragraph',
            $method->description
        );
        $this->assertEquals('name', $method->parameters[0]->name);
        $this->assertEquals('string', $method->parameters[0]->type);
        $this->assertFalse($method->parameters[0]->isOptional);
        $this->assertEquals('\CommonTypes\Uuid', $method->parameters[1]->type);
    }
}
