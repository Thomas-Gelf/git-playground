<?php

namespace gipfl\Tests\DistanceRouter;

use gipfl\DistanceRouter\Route;
use gipfl\DistanceRouter\RouteList;
use gipfl\DistanceRouter\RoutingTable;
use PHPUnit\Framework\TestCase;

class RoutingTableTest extends TestCase
{
    public function testEmptyTableFindsNoTarget()
    {
        $table = new RoutingTable();
        $this->assertFalse($table->active->hasRouteTo('nowhere'));
    }

    public function testMissingTargetGivesNull()
    {
        $table = new RoutingTable();
        $this->assertNull($table->active->getRouteTo('nowhere'));
    }

    public function testValidTargetGivesCorrectRoute()
    {
        $table = new RoutingTable();
        $table->addCandidate(new Route('target.example.com', 'router.example.com', 3));
        $table->addCandidate(new Route('target.example.com', 'other.example.com', 7));
        $this->assertEquals('router.example.com', $table->active->getRouteTo('target.example.com')->via);
    }

    public function testRouteGivesFirstCandidateWithSameDistance()
    {
        $table = new RoutingTable();
        $table->addCandidate(new Route('target.example.com', 'other.example.com', 7));
        $table->addCandidate(new Route('target.example.com', 'router1.example.com', 3));
        $table->addCandidate(new Route('target.example.com', 'router2.example.com', 3));
        $table->removeCandidate(new Route('target.example.com', 'router1.example.com', 3));
        $table->addCandidate(new Route('target.example.com', 'router3.example.com', 3));
        $this->assertEquals('router2.example.com', $table->active->getRouteTo('target.example.com')->via);
    }

    public function testRemovingUnknownCandidateDoesNotFail()
    {
        $table = new RoutingTable();
        $table->removeCandidate(new Route('target.example.com', 'router2.example.com', 3));
        $table->addCandidate(new Route('target.example.com', 'router1.example.com', 2));
        $this->assertEquals(2, $table->active->getRouteTo('target.example.com')->distance);
    }

    public function testCandidatesCanBeAddedFromList()
    {
        $list = new RouteList([
            new Route('target1.example.com', 'other.example.com', 7),
            new Route('target2.example.com', 'router1.example.com', 3),
        ]);
        $table = new RoutingTable();
        $table->addCandidatesFromList($list);
        $this->assertEquals('other.example.com', $table->active->getRouteTo('target1.example.com')->via);
    }

    public function testMultipleRouteListsCanBeApplied()
    {
        $list1 = new RouteList([
            new Route('target1.example.com', 'router1.example.com', 5),
            new Route('target2.example.com', 'router1.example.com', 3),
            new Route('target4.example.com', 'router1.example.com', 3),
        ]);
        $list2 = new RouteList([
            new Route('target1.example.com', 'router2.example.com', 7),
            new Route('target2.example.com', 'router2.example.com', 3),
            new Route('target3.example.com', 'router2.example.com', 3),
        ]);
        $table = new RoutingTable();
        $table->addCandidatesFromList($list1);
        $table->addCandidatesFromList($list2);
        $this->assertEquals('router1.example.com', $table->active->getRouteTo('target1.example.com')->via);
        $this->assertEquals('router1.example.com', $table->active->getRouteTo('target2.example.com')->via);
        $this->assertEquals('router2.example.com', $table->active->getRouteTo('target3.example.com')->via);
        $this->assertEquals('router1.example.com', $table->active->getRouteTo('target4.example.com')->via);
        $this->assertNull($table->active->getRouteTo('target5.example.com'));
    }

    public function testRouteListDiffIsAppliedCorrectly()
    {
        $list1 = new RouteList([
            new Route('target1.example.com', 'router1.example.com', 5),
            new Route('target2.example.com', 'router1.example.com', 3),
            new Route('target4.example.com', 'router1.example.com', 3),
        ]);
        $list2 = new RouteList([
            new Route('target1.example.com', 'router2.example.com', 7),
            new Route('target2.example.com', 'router2.example.com', 3),
            new Route('target3.example.com', 'router2.example.com', 3),
        ]);
        $list2new = new RouteList([
            new Route('target1.example.com', 'router2.example.com', 1),
            new Route('target2.example.com', 'router2.example.com', 3),
            new Route('target6.example.com', 'router2.example.com', 3),
        ]);
        $table = new RoutingTable();
        $table->addCandidatesFromList($list1);
        $table->addCandidatesFromList($list2);
        $this->assertEquals('router1.example.com', $table->active->getRouteTo('target1.example.com')->via);
        $this->assertEquals('router1.example.com', $table->active->getRouteTo('target2.example.com')->via);
        $this->assertEquals('router2.example.com', $table->active->getRouteTo('target3.example.com')->via);
        $this->assertEquals('router1.example.com', $table->active->getRouteTo('target4.example.com')->via);
        $this->assertNull($table->active->getRouteTo('target5.example.com'));
        $table->applyDiff($list2, $list2new);
        $this->assertNull($table->active->getRouteTo('target3.example.com'));
        $this->assertEquals('router2.example.com', $table->active->getRouteTo('target1.example.com')->via);
        $this->assertEquals('router1.example.com', $table->active->getRouteTo('target2.example.com')->via);
        $this->assertEquals('router2.example.com', $table->active->getRouteTo('target6.example.com')->via);
    }
}
