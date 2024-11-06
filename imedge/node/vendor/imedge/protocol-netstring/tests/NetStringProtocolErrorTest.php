<?php

namespace IMEdge\Tests\Protocol\NetString;

use IMEdge\Protocol\NetString\NetStringProtocolError;
use PHPUnit\Framework\TestCase;

class NetStringProtocolErrorTest extends TestCase
{
    public function testCanBeThrown(): void
    {
        $this->expectException(NetStringProtocolError::class);
        throw new NetStringProtocolError();
    }
}
