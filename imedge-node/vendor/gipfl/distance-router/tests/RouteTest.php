<?php

namespace gipfl\Tests\DistanceRouter;

use gipfl\DistanceRouter\Route;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    public function testZeroDistanceIsNotAllowed()
    {
        $this->expectException(InvalidArgumentException::class);
        new Route('target.example.com', 'router.example.com', 0);
    }

    public function testEqualRoutesAreEqual()
    {
        $this->assertTrue(
            (new Route('target.example.com', 'router.example.com', 1))
                ->equals(new Route('target.example.com', 'router.example.com', 1))
        );
    }

    public function testRoutesWithDifferentDistanceAreNotEqual()
    {
        $this->assertFalse(
            (new Route('target.example.com', 'router.example.com', 1))
                ->equals(new Route('target.example.com', 'router.example.com', 2))
        );
    }

    public function testRoutesWithDifferentViaAreNotEqual()
    {
        $this->assertFalse(
            (new Route('target1.example.com', 'router1.example.com', 1))
                ->equals(new Route('target1.example.com', 'router2.example.com', 1))
        );
    }

    public function testRoutesWithDifferentTargetAreNotEqual()
    {
        $this->assertFalse(
            (new Route('target1.example.com', 'router.example.com', 1))
                ->equals(new Route('target2.example.com', 'router.example.com', 1))
        );
    }

    public function testRouteCanBeSerialized()
    {
        $route = new Route('target.example.com', 'router.example.com', 1);
        $this->assertEquals((object) [
            'target' => 'target.example.com',
            'via' => 'router.example.com',
            'distance' => 1,
        ], $route->jsonSerialize());
    }

    public function testRouteCanBeUnSerialized()
    {
        $route = Route::fromSerialization((object) [
            'target' => 'target.example.com',
            'via' => 'router.example.com',
            'distance' => 1,
        ]);
        $this->assertEquals('target.example.com', $route->target);
        $this->assertEquals('router.example.com', $route->via);
        $this->assertEquals(1, $route->distance);
    }
}
