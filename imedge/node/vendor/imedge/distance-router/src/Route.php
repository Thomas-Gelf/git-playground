<?php

namespace IMEdge\DistanceRouter;

use IMEdge\Json\JsonSerialization;
use InvalidArgumentException;
use RuntimeException;

class Route implements JsonSerialization
{
    /** @readonly */
    public string $target;
    /** @readonly */
    public int $distance;
    /** @readonly */
    public string $via;

    final public function __construct(string $target, string $via, int $distance)
    {
        $this->target = $target;
        if ($distance > 0) {
            $this->distance = $distance;
        } else {
            throw new InvalidArgumentException("Invalid distance: $distance");
        }
        $this->via = $via;
    }

    public function equals(Route $route): bool
    {
        return $this->target === $route->target && $this->hasSameViaAndDistance($route);
    }

    public function hasSameVia(Route $route): bool
    {
        return $this->via === $route->via;
    }

    public function hasSameViaAndDistance(Route $route): bool
    {
        return $route->distance === $this->distance && $this->hasSameVia($route);
    }

    public static function fromSerialization($any): Route
    {
        if (! is_object($any)) {
            throw new RuntimeException('Cannot unserialize Route from ' . get_debug_type($any));
        }

        return new static(
            $any->target ?? throw new RuntimeException('Route target is required'),
            $any->via ?? throw new RuntimeException('Route via is required'),
            $any->distance ?? throw new RuntimeException('Route distance is required')
        );
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'target'   => $this->target,
            'via'      => $this->via,
            'distance' => $this->distance,
        ];
    }
}
