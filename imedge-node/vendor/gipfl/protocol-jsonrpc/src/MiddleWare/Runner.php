<?php

namespace gipfl\Protocol\JsonRpc\MiddleWare;

use gipfl\Protocol\JsonRpc\Notification;

class Runner
{
    /** @var MiddlewareInterface[] */
    protected $middleWares = [];

    public function pushMiddleware(MiddlewareInterface $middleware)
    {
        $this->middleWares[] = $middleware;
        return $this;
    }

    public function handle(Notification $notification)
    {
        if ($notification instanceof Notification) {
            return;
        }
    }

    public function handleNotification(Notification $notification)
    {
        foreach ($this->middleWares as $middleWare) {
            $middleWare->handleNotification($notification);
        }
    }
}
