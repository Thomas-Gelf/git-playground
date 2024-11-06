<?php

namespace IMEdge\Tests\RrdStructure;

use IMEdge\RrdStructure\RraAggregation;
use IMEdge\RrdStructure\RrdInfo;
use PHPUnit\Framework\TestCase;

use function json_decode as decode;
use function json_encode as encode;

class RrdInfoTest extends TestCase
{
    protected const RELATIVE_FILENAME = 'aaa/aaa52497e0614e0e8c6460fe6f1490b1.rrd';
    protected const DATA_DIR = '/rrd/data/lab1/rrdcached/data';

    protected function loadSample(string $name): string
    {
        $filename = __DIR__ . "/sample/$name";
        $content = file_get_contents($filename);
        if ($content === false) {
            throw new \RuntimeException("Failed to load $filename");
        }

        return $content;
    }

    public function testParsesRrdtoolInfo(): void
    {
        $this->runContentTests(RrdInfo::parse($this->loadSample('rrdtool-info.en_US')), self::RELATIVE_FILENAME);
    }

    public function testParsesRrdcachedInfo(): void
    {
        $this->runContentTests(
            RrdInfo::parse($this->loadSample('rrdcached-info.en_US')),
            self::DATA_DIR . '/' . self::RELATIVE_FILENAME
        );
        $this->runContentTests(
            RrdInfo::parse($this->loadSample('rrdcached-info.en_US'), self::DATA_DIR),
            self::RELATIVE_FILENAME
        );
    }

    public function testCanBeRestoredFromJson(): void
    {
        $this->runContentTests(
            RrdInfo::fromSerialization(decode((string) encode($this->defaultSample()))),
            self::RELATIVE_FILENAME,
            false
        );
    }

    public function testParsesLocalizedRrdtoolInfo(): void
    {
        $infoEn = RrdInfo::parse($this->loadSample('rrdtool-info.en_US'));
        $infoDe = RrdInfo::parse($this->loadSample('rrdtool-info.de_DE'));
        $this->assertEquals($infoEn->jsonSerialize(), $infoDe->jsonSerialize());
    }

    public function testDsCanBeRenderedFromInfo(): void
    {
        $this->assertEquals(
            'DS:ifInUcastPkts:DERIVE:8640:0:U'
            . ' DS:ifOutUcastPkts:DERIVE:8640:0:U'
            . ' DS:ifInNUcastPkts:DERIVE:8640:0:U'
            . ' DS:ifOutNUcastPkts:DERIVE:8640:0:U',
            (string) $this->defaultSample()->getDsList()
        );
    }

    public function testRraSetCanBeRenderedFromInfo(): void
    {
        $this->assertEquals(
            'RRA:AVERAGE:0.5:1:2880 RRA:AVERAGE:0.5:5:2880 RRA:MAX:0.5:1:2880'
            . ' RRA:MAX:0.5:5:2880 RRA:MIN:0.5:1:2880 RRA:MIN:0.5:5:2880',
            (string) $this->defaultSample()->getRraSet()
        );
    }

    protected function defaultSample(): RrdInfo
    {
        return RrdInfo::parse($this->loadSample('rrdtool-info.en_US'));
    }

    protected function runContentTests(RrdInfo $info, string $expectedFilename, bool $withVolatile = true): void
    {
        $this->assertEquals('0003', $info->getRrdVersion());
        $this->assertEquals($expectedFilename, $info->getFilename());
        $this->assertEquals(60, $info->getStep());
        $this->assertEquals(3760, $info->getHeaderSize());
        $this->assertEquals(4 * 6 * 2880 * 8, $info->getDataSize()); // DS count * RRA count * rows * 8 Bytes per entry
        $this->assertEquals(556720, $info->getHeaderSize() + $info->getDataSize()); // File size
        $this->assertEquals(1721290732, $info->getLastUpdate());
        $this->assertEquals(4, $info->countDataSources());
        $this->assertEquals(6, count($info->getRraSet()->getRras()));
        $ds = $info->getDsList()->requireDs('ifInUcastPkts');
        $this->assertNull($ds->getMax());
        $this->assertEquals(0, $ds->getMin());
        $this->assertEquals(8640, $ds->getHeartbeat());
        $this->assertEquals('DERIVE', $ds->getType());
        $rra = $info->getRraSet()->getRraByIndex(1);
        $this->assertInstanceOf(RraAggregation::class, $rra);
        if ($withVolatile) {
            $this->assertEquals(2013, $rra->getCurrentRow());
        }
        $this->assertEquals(2880, $rra->getRows());
        $this->assertEquals(0.5, $rra->getXFilesFactor());
        $this->assertEquals(5, $rra->getSteps());
        $this->assertEquals(2880 * 8, $rra->getDataSize()); // 8 Bytes per entry
    }
}
