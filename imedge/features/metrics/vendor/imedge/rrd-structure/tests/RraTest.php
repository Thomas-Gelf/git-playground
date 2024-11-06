<?php

namespace IMEdge\Tests\RrdStructure;

use IMEdge\RrdStructure\Rra;
use PHPUnit\Framework\TestCase;

class RraTest extends TestCase
{
    public function testParseSimpleRraString(): void
    {
        $str = 'RRA:MIN:0.5:21600:5840';
        $rra = Rra::fromString($str);
        $this->assertEquals($str, $rra->toString());
    }
}
