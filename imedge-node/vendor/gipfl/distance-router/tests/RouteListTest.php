<?php

namespace gipfl\Tests\DistanceRouter;

use gipfl\DistanceRouter\Route;
use gipfl\DistanceRouter\RouteList;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RouteListTest extends TestCase
{
    public function testRouteListFindsSpecificRoute()
    {
        $list = new RouteList([
            new Route('target1.example.com', 'router1.example.com', 5),
            new Route('target2.example.com', 'router1.example.com', 3),
            new Route('target4.example.com', 'router1.example.com', 3),
        ]);
        $this->assertEquals('router1.example.com', $list->getRouteTo('target1.example.com')->via);
    }

    public function testRouteListGivesNullForMissingRoute()
    {
        $list = new RouteList([
            new Route('target1.example.com', 'router1.example.com', 5),
            new Route('target2.example.com', 'router1.example.com', 3),
            new Route('target4.example.com', 'router1.example.com', 3),
        ]);
        $this->assertNull($list->getRouteTo('target3.example.com'));
    }

    public function testInitializingWithTheSameTargetTwiceFails()
    {
        $this->expectException(InvalidArgumentException::class);
        new RouteList([
            new Route('target1.example.com', 'router1.example.com', 5),
            new Route('target1.example.com', 'router2.example.com', 5),
        ]);
    }

    public function testRouteListCanBeSerialized()
    {
        $list = new RouteList([
            new Route('target1.example.com', 'router1.example.com', 1),
            new Route('target2.example.com', 'router2.example.com', 3),
        ]);
        $this->assertEquals(json_encode([(object) [
            'target'   => 'target1.example.com',
            'via'      => 'router1.example.com',
            'distance' => 1,
        ], (object) [
            'target'   => 'target2.example.com',
            'via'      => 'router2.example.com',
            'distance' => 3,
        ]]), json_encode($list->jsonSerialize()));
    }

    public function testRouteListCanBeUnSerialized()
    {
        $list = RouteList::fromSerialization([(object) [
            'target'   => 'target1.example.com',
            'via'      => 'router1.example.com',
            'distance' => 1,
        ], (object) [
            'target'   => 'target2.example.com',
            'via'      => 'router2.example.com',
            'distance' => 3,
        ]]);
        $this->assertEquals('router1.example.com', $list->getRouteTo('target1.example.com')->via);
        $this->assertEquals(3, $list->getRouteTo('target2.example.com')->distance);
    }
}
