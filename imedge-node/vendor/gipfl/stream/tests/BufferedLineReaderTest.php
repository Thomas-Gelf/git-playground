<?php

use gipfl\Stream\BufferedLineReader;
use gipfl\Test\AsyncTestUtils;
use PHPUnit\Framework\TestCase;

class BufferedLineReaderTest extends TestCase
{
    use AsyncTestUtils;

    public function testSimple()
    {
        $reader = new BufferedLineReader("\n", $this->loop());
        $lines = [];
        $reader->on('line', function ($line) use (&$lines) {
            $lines[] = $line;
        });
        $this->loop->futureTick(function () use ($reader, &$lines) {
            $reader->write("a\nb\nc\n");
            $this->loop()->addTimer(1, function () use (&$lines) {
                $this->assertEquals(['a', 'b', 'c'], $lines);
            });
        });
        $this->loop()->run();
    }
}
