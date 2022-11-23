
* LISTEN_FDS -> number of listening FDS (or not set)
* SD_LISTEN_FDS_START -> first FD
* LISTEN_FDNAMES -> split at ':' 
FileDescriptorName=

$handle = fopen('php://fd/' . $fd, 'r+');
var_dump(
    stream_socket_get_name($handle, false),
    stream_get_meta_data($handle)
);

$client = stream_socket_accept($handle, 0);

reactphp/socket
---------------

Systemd Socket Activation - File Descriptor with HTTP Server #164

* https://github.com/reactphp/socket/issues/164


clue/FdServer.php
-----------------

* https://gist.github.com/clue/fa4f1ddf26f2f8727232215a0f700e09

```php
<?php

namespace React\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;
use RuntimeException;

final class FdServer extends EventEmitter implements ServerInterface
{
    private $master;
    private $loop;
    private $listening = false;

    public function __construct($fd, LoopInterface $loop)
    {
        $this->loop = $loop;

        $this->master = @fopen('php://fd/' . $fd, 'r+');
        if (false === $this->master) {
            // TODO:
            $errno = $errstr = null;
            throw new RuntimeException('Failed to listen on FD ' . $fd . ': ' . $errstr, $errno);
        }

        $meta = stream_get_meta_data($this->master);
        if (!isset($meta['stream_type']) || $meta['stream_type'] !== 'tcp_socket') {
            fclose($this->master);
            throw new \UnexpectedValueException('Failed to listen on FD ' . $fd . ' because it does not look like a socket file descriptor');
        }

        // TODO: check unix?! stream_socket_get_name() ?
        stream_set_blocking($this->master, 0);

        $this->resume();
    }

    public function getAddress()
    {
        if (!is_resource($this->master)) {
            return null;
        }

        $address = stream_socket_get_name($this->master, false);

        // check if this is an IPv6 address which includes multiple colons but no square brackets
        $pos = strrpos($address, ':');
        if ($pos !== false && strpos($address, ':') < $pos && substr($address, 0, 1) !== '[') {
            $port = substr($address, $pos + 1);
            $address = '[' . substr($address, 0, $pos) . ']:' . $port;
        }

        return 'tcp://' . $address;
    }

    public function pause()
    {
        if (!$this->listening) {
            return;
        }

        $this->loop->removeReadStream($this->master);
        $this->listening = false;
    }

    public function resume()
    {
        if ($this->listening || !is_resource($this->master)) {
            return;
        }

        $that = $this;
        $this->loop->addReadStream($this->master, function ($master) use ($that) {
            $newSocket = @stream_socket_accept($master);
            if (false === $newSocket) {
                $that->emit('error', array(new RuntimeException('Error accepting new connection')));

                return;
            }
            $that->handleConnection($newSocket);
        });
        $this->listening = true;
    }

    public function close()
    {
        if (!is_resource($this->master)) {
            return;
        }

        $this->pause();
        fclose($this->master);
        $this->removeAllListeners();
    }

    /** @internal */
    public function handleConnection($socket)
    {
        $this->emit('connection', array(
            new Connection($socket, $this->loop)
        ));
    }
}
```