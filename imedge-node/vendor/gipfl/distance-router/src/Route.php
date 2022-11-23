<?php

namespace gipfl\DistanceRouter;

use gipfl\Json\JsonSerialization;
use InvalidArgumentException;

class Route implements JsonSerialization
{
    /** @readonly */
    public string $target;
    /** @readonly */
    public int $distance;
    /** @readonly */
    public string $via;

    public function __construct(string $target, string $via, int $distance)
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
        return new Route($any->target, $any->via, $any->distance);
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
