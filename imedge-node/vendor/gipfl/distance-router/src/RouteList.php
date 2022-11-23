<?php

namespace gipfl\DistanceRouter;

use gipfl\Json\JsonSerialization;
use InvalidArgumentException;

class RouteList implements JsonSerialization
{
    /**
     * @readonly
     * @var array<string, Route>
     */
    public array $routes = [];

    public function __construct(array $routes = [])
    {
        foreach ($routes as $route) {
            $this->addRoute($route);
        }
    }

    public function getRouteTo(string $target): ?Route
    {
        return $this->routes[$target] ?? null;
    }

    public function hasRouteTo(string $target): bool
    {
        return isset($this->routes[$target]);
    }

    public function forgetTarget(string $target)
    {
        unset($this->routes[$target]);
    }

    public function setRoute(Route $route)
    {
        $this->routes[$route->target] = $route;
    }

    public function addRoute(Route $route)
    {
        if (isset($this->routes[$route->target])) {
            throw new InvalidArgumentException(sprintf(
                'Cannot set route for %s twice',
                $route->target
            ));
        }

        $this->setRoute($route);
    }

    public static function fromSerialization($any): RouteList
    {
        $self = new RouteList();
        foreach ((array) $any as $route) {
            $self->addRoute(Route::fromSerialization($route));
        }

        return $self;
    }

    public function jsonSerialize(): array
    {
        return array_values($this->routes);
    }
}
