<?php

namespace gipfl\Tests\Prototol\JsonRpc;

use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\TestCase;

class NotificationTest extends TestCase
{
    protected $examples = [
        '{"jsonrpc":"2.0","method":"update","params":[1,2,3,4,5]}',
    ];

    public function testParsesNotificationWithPositionalParams()
    {
        $packet = $this->parseExample(0);
        $this->assertInstanceOf(Notification::class, $packet);
        $this->assertEquals('update', $packet->getMethod());
        $this->assertEquals([1, 2, 3, 4, 5], $packet->getParams());
    }

    public function testRendersNotificationWithPositionalParams()
    {
        $this->assertEquals(
            $this->examples[0],
            $this->parseExample(0)->toString()
        );
    }
}
