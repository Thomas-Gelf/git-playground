<?php

namespace gipfl\Tests\Prototol\JsonRpc;

use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\JsonRpc\Packet;
use gipfl\Protocol\JsonRpc\Response;
use gipfl\Protocol\JsonRpc\TestCase;
use React\EventLoop\Factory;
use React\Stream\DuplexResourceStream;

class ConnectionTest extends TestCase
{
    protected $examples = [
        '{"jsonrpc":"2.0","method":"math.subtract","params":[42,23],"id":1}',
    ];

    public function testAcceptsASimpleDuplexStream()
    {
        $errors = [];
        $this->collectErrorsForNotices($errors);
        $loop = Factory::create();
        list($sockA, $sockB) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $streamA = new DuplexResourceStream($sockA, $loop);
        $connection = new Connection();
        $connection->handle($streamA);
        $this->throwEventualErrors($errors);
        $this->assertInstanceOf(Connection::class, $connection); // Just to have an assertion
    }

    public function testRequestForMissingMethod()
    {
        $errors = [];
        $this->collectErrorsForNotices($errors);
        $loop = Factory::create();
        list($sockA, $sockB) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        $streamA = new DuplexResourceStream($sockA, $loop);
        $streamB = new DuplexResourceStream($sockB, $loop);
        $connectionA = new Connection();
        $connectionA->handle($streamB);
        $connectionB = new Connection();
        $connectionB->handle($streamA);
        $connectionA->request('test', [
            'some' => 'parameter'
        ]);
        $this->failAfterSeconds(1, $loop);
        $loop->futureTick(function () use ($connectionA, $streamB, $loop) {
            $streamB->on('data', function ($data) use ($loop) {
                $response = Packet::decode($data);
                $this->assertInstanceOf(Response::class, $response);
                $this->assertTrue($response->isError());
                $this->assertEquals(-32601, $response->getError()->getCode());
                $loop->stop();
            });
        });
        $loop->run();
        $this->throwEventualErrors($errors);
    }

    public function testARequestWithNoRelatedHandlerGetsAnError()
    {
        $errors = [];
        $this->collectErrorsForNotices($errors);
        $loop = Factory::create();
        list($sockA, $sockB) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        $streamA = new DuplexResourceStream($sockA, $loop);
        $streamB = new DuplexResourceStream($sockB, $loop);
        $connection = new Connection();
        $connection->handle($streamA);
        $this->failAfterSeconds(2, $loop);
        $loop->futureTick(function () use ($streamB, $loop) {
            $packet = $this->parseExample(0);
            $streamB->write($packet->toString());
            $streamB->on('data', function ($data) use ($loop) {
                $response = Packet::decode($data);
                $this->assertInstanceOf(Response::class, $response);
                $this->assertTrue($response->isError());
                $this->assertEquals(-32601, $response->getError()->getCode());
                $loop->stop();
            });
        });
        $loop->run();
        $this->throwEventualErrors($errors);
    }

    public function testConnectionDoesNotLeakMemory()
    {
        $errors = [];
        $this->collectErrorsForNotices($errors);
        $loop = Factory::create();
        list($sockA, $sockB) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        $streamA = new DuplexResourceStream($sockA, $loop);
        $streamB = new DuplexResourceStream($sockB, $loop);
        $connection = new Connection();
        $mem = memory_get_usage(true);
        $connection->handle($streamA);
        $this->failAfterSeconds(2, $loop);
        $loop->futureTick(function () use ($streamB, $loop, $mem) {
            $packet = $this->parseExample(0);
            $streamB->write($packet->toString());
            $streamB->on('data', function ($data) use ($loop, $mem) {
                $response = Packet::decode($data);
                $this->assertInstanceOf(Response::class, $response);
                $this->assertTrue($response->isError());
                $this->assertEquals(-32601, $response->getError()->getCode());
                $loop->stop();
                gc_collect_cycles();
                $diff = memory_get_usage(true) - $mem;
                $this->assertEquals($diff, 0);
            });
        });
        $loop->run();
        $this->throwEventualErrors($errors);
    }
}
