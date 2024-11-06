<?php

namespace IMEdge\Tests\Protocol\NetString;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\WritableBuffer;
use IMEdge\Protocol\NetString\NetStringConnection;
use IMEdge\Protocol\NetString\NetStringReader;
use IMEdge\Protocol\NetString\NetStringWriter;
use PHPUnit\Framework\TestCase;

class NetStringReadmeTest extends TestCase
{
    protected function setUp(): void
    {
        TestHelper::beginCatchingOutput();
    }

    public function testSimpleReaderExample(): void
    {
        $netString = new NetStringReader(new ReadableBuffer('5:Hello,6:world!,'));
        foreach ($netString->packets() as $packet) {
            var_dump($packet);
        }

        $this->assertEquals(
            'string(5) "Hello"' . "\n" .
            'string(6) "world!"' . "\n",
            TestHelper::getCatchedOutput()
        );
    }

    public function testSimpleWriterExample(): void
    {

        $netString = new NetStringWriter($out = new WritableBuffer());
        $netString->write('Hello');
        $netString->write(' ');
        $netString->write('World!');
        $netString->close();
        var_dump($out->buffer());

        $this->assertEquals(
            'string(21) "5:Hello,1: ,6:World!,"' . "\n",
            TestHelper::getCatchedOutput()
        );
    }

    public function testSimplePipeReadmeExample(): void
    {
        $netString = new NetStringConnection(new ReadableBuffer('5:Hello,6:world!,'), $out = new WritableBuffer());
        $netString->write('Hi!');
        foreach ($netString->packets() as $packet) {
            var_dump($packet);
        }
        $out->close();
        var_dump($out->buffer());

        $this->assertEquals(
            'string(5) "Hello"' . "\n" .
            'string(6) "world!"' . "\n" .
            'string(6) "3:Hi!,"' . "\n",
            TestHelper::getCatchedOutput()
        );
    }
}
