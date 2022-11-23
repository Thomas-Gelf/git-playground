<?php

namespace IcingaMetrics\Async;

use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class AsyncPromise
{
    public static function resolveNext($value = null): PromiseInterface
    {
        $deferred = new Deferred();
        Loop::futureTick(function ()  use ($deferred, $value) {
            $deferred->resolve($value);
        });

        return $deferred->promise();
    }
}
