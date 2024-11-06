<?php

namespace IMEdge\DistanceRouter;

use IMEdge\Json\JsonSerialization;
use InvalidArgumentException;
use RuntimeException;

class RouteList implements JsonSerialization
{
    /**
     * @readonly
     * @var array<string, Route>
     */
    public array $routes = [];

    /**
     * @param Route[] $routes
     */
    final public function __construct(array $routes = [])
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

    public function forgetTarget(string $target): void
    {
        unset($this->routes[$target]);
    }

    public function setRoute(Route $route): void
    {
        $this->routes[$route->target] = $route;
    }

    public function addRoute(Route $route): void
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
        if (! is_array($any)) {
            throw new RuntimeException('Cannot unserialize RouteList from ' . get_debug_type($any));
        }
        $self = new static();
        foreach ((array) $any as $route) {
            $self->addRoute(Route::fromSerialization($route));
        }

        return $self;
    }

    /**
     * @return Route[]
     */
    public function jsonSerialize(): array
    {
        return array_values($this->routes);
    }
}
