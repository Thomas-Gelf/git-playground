<?php

namespace gipfl\Protocol\JsonRpc\Handler;

use gipfl\Protocol\JsonRpc\Error;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\Request;

class FailingPacketHandler implements JsonRpcHandler
{
    /** @var Error */
    protected $error;

    public function __construct(Error $error)
    {
        $this->error = $error;
    }

    public function processNotification(Notification $notification, JsonRpcConnection $connection)
    {
        // We silently ignore them
    }

    public function processRequest(Request $request, JsonRpcConnection $connection)
    {
        return $this->error;
    }
}
