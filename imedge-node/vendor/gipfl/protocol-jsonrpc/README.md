gipfl\\Protocol\\JsonRpc
========================

JsonRpc implementation.

```php

use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\JsonRpc\Packet;

$connection = new Connection();

/** @var \React\EventLoop\LoopInterface $loop */
/** @var \React\Stream\DuplexStreamInterface $stream */

$connection->handle($stream);
$stream->on('data', function ($data) use ($loop) {
    $packet = Packet::decode($data);
});
```

```php
<?php

class ConnectionHandler
{
    /** @var SplObjectStorage */
    protected $connections;

    protected function registerConnection(ConnectionInterface $connection)
    {
        $this->connections->attach($connection);
        $connection->on('notification', function (Notification $notification) {
            
        });
        $connection->on('close', function () use ($connection) {
            $this->removeConnection($connection);
        });
    }
}
/*


$controller = RequestFrontController();
$controller->append(new RequestLogger());
$controller->addMiddle
RequestMiddleWare()
RequestRouter()
$router->dispatch($request); // or notification
$router->add();

$server->on('connection', function(ConnectionInterface $connection) {
    $this->registerConnection($connection);
});
*/
```


On Connection
-------------



```php

use \gipfl\Protocol\JsonRpc\JsonRpcConnectionHandler;
/** @var React\ */

/** @var \React\Stream\DuplexStreamInterface $stream */
$connectionHandler = new JsonRpcConnectionHandler($stream);
$connectionHandler->sdaf();

```
