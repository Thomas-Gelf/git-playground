<?php

namespace gipfl\Protocol\JsonRpc\MiddleWare;

use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\Request;
use gipfl\Protocol\JsonRpc\Response;

interface RequestHandlerInterface
{
    /**
     * @param Notification $notification
     * @return void
     */
    public function handleNotification(Notification $notification);

    /**
     * @param Request $request
     * @return Response
     */
    public function handleRequest(Request $request);
}
