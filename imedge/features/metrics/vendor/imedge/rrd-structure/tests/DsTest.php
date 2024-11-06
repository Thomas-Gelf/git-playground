<?php

namespace IMEdge\Tests\RrdStructure;

use IMEdge\Json\JsonString;
use IMEdge\RrdStructure\Ds;
use PHPUnit\Framework\TestCase;

class DsTest extends TestCase
{
    public function testParsesSimpleDsDefinitions(): void
    {
        $ds = new Ds('temperature', 'GAUGE', 8640);
        $this->assertEquals('DS:temperature:GAUGE:8640:U:U', $ds->toString());
        $ds = new Ds('temperature', 'GAUGE', 8640, 0);
        $this->assertEquals('DS:temperature:GAUGE:8640:0:U', $ds->toString());
        $ds = new Ds('temperature', 'GAUGE', 8640, null, 10000);
        $this->assertEquals('DS:temperature:GAUGE:8640:U:10000', $ds->toString());
        $ds = new Ds('temperature', 'GAUGE', 8640, -371, 10000);
        $this->assertEquals('DS:temperature:GAUGE:8640:-371:10000', $ds->toString());
    }

    public function testAliasCanBeRetrieved(): void
    {
        $ds = new Ds('temperature', 'GAUGE', 8640, null, null, null, 'An alias');
        $this->assertEquals('An alias', $ds->getAlias());
    }

    public function testAliasDoesNotInfluenceRendering(): void
    {
        $ds = new Ds('temperature', 'GAUGE', 8640);
        $ds->setAlias('An alias');
        $this->assertEquals('DS:temperature:GAUGE:8640:U:U', $ds->toString());
    }

    public function testParsesAndRendersMappedName(): void
    {
        $ds = new Ds('temperature', 'GAUGE', 8640, -371, 10000, 'new-temp');
        $this->assertEquals('DS:temperature=new-temp:GAUGE:8640:-371:10000', $ds->toString());
    }

    public function testCanBeSerializedToJson(): void
    {
        $ds = new Ds('temperature', 'GAUGE', 8640, -371, 10000);
        $json = '{"name":"temperature","type":"GAUGE","heartbeat":8640,"min":-371,"max":10000,"alias":null,'
            . '"mappedName":null}';
        $this->assertEquals($json, JsonString::encode($ds));
    }

    public function testRestoresFromJson(): void
    {
        $json = '{"name":"temperature","type":"GAUGE","heartbeat":8640,"min":-371,"max":10000,"alias":null,'
            . '"mappedName":null}';
        // @phpstan-ignore-next-line
        $ds = Ds::fromSerialization(JsonString::decode($json));
        $this->assertEquals('temperature', $ds->getName());
        $this->assertEquals('GAUGE', $ds->getType());
        $this->assertEquals(8640, $ds->getHeartbeat());
        $this->assertEquals(-371, $ds->getMin());
        $this->assertEquals(10000, $ds->getMax());
        $this->assertEquals(null, $ds->getAlias());
        $this->assertEquals(null, $ds->getMappedName());
    }
}
