<?php

namespace gipfl\Tests\Prototol\JsonRpc\Handler;

use gipfl\Protocol\JsonRpc\Handler\RpcContext;
use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\Request;

class SampleMetaData extends RpcContext
{
    public function getNamespace()
    {
        return 'sample';
    }

    public function isAccessible()
    {
        return true;
    }

    /**
     * @rpcParam int    $someNumber     A random number
     * @rpcParam string $andSomeString  And a textual
     *    parameter
     * @param Request $request
     */
    public function testRequest(Request $request)
    {
    }

    /**
     * @rpcParam boolean $aBoolean       This parameter is a boolean
     *   value with a multiline description. Let's see how this behaves
     * @param Notification $notification
     */
    public function someTestNotification(Notification $notification)
    {
    }
}
