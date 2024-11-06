<?php

namespace IMEdge\DistanceRouter;

class RoutingTable
{
    /** @readonly */
    public RouteList $active;

    /** @var array<string, array<int, array<string, Route>>> */
    protected array $candidates = [];

    public function __construct()
    {
        $this->active = new RouteList();
    }

    public function addCandidate(Route $route): void
    {
        $this->candidates[$route->target] ??= [];
        $routes = &$this->candidates[$route->target];
        $distance = $route->distance;
        $routes[$distance][$route->via] = $route;
        reset($routes[$distance]);
        $minDistance = min(array_keys($routes));
        $this->active->setRoute(current($routes[$minDistance]));
    }

    public function addCandidatesFromList(RouteList $list): void
    {
        foreach ($list->routes as $route) {
            $this->addCandidate($route);
        }
    }

    public function removeCandidate(Route $route): void
    {
        if (! isset($this->candidates[$route->target])) {
            return;
        }

        $routes = &$this->candidates[$route->target];
        $distance = $route->distance;
        if (isset($routes[$distance][$route->via])) {
            unset($routes[$distance][$route->via]);
            reset($routes[$distance]);
        }
        if (empty($routes[$distance])) {
            unset($routes[$distance]);
        }

        if (empty($routes)) {
            unset($this->candidates[$route->target]);
            $this->active->forgetTarget($route->target);
        } else {
            $minDistance = min(array_keys($routes));
            // Hint: this 'if' here satisfies PHPStan, but it is always true
            if ($route = current($routes[$minDistance])) {
                $this->active->setRoute($route);
            }
        }
    }

    public function applyDiff(RouteList $old, RouteList $new): void
    {
        foreach ($old->routes as $route) {
            if ($newRoute = $new->getRouteTo($route->target)) {
                if (!$newRoute->hasSameViaAndDistance($route)) {
                    $this->removeCandidate($route);
                    $this->addCandidate($newRoute);
                }
            } else {
                $this->removeCandidate($route);
            }
        }
        foreach ($new->routes as $route) {
            if (!$old->hasRouteTo($route->target)) {
                $this->addCandidate($route);
            }
        }
    }
}
