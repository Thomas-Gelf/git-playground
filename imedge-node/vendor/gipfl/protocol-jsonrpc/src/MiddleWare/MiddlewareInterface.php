<?php

namespace gipfl\Protocol\JsonRpc\MiddleWare;

use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\Request;
use gipfl\Protocol\JsonRpc\Response;

interface MiddlewareInterface
{
    /**
     * @param Notification $notification
     * @return void
     */
    public function processNotification(Notification $notification);

    /**
     * @param Request $request
     * @return Response
     */
    public function processRequest(Request $request);
}
