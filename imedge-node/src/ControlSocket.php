<?php

namespace IcingaDataNode;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\Socket\UnixServer;
use React\Stream\Util;

use function file_exists;
use function umask;
use function unlink;

class ControlSocket implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected ?UnixServer $server = null;

    public function __construct(
        protected readonly string $path
    ) {
        $this->removeOrphanedSocketFile();
    }

    public function run(): void
    {
        $this->listen();
    }

    protected function listen(): void
    {
        $old = umask(0000);
        $server = new UnixServer('unix://' . $this->path);
        umask($old);
        Util::forwardEvents($server, $this, ['connection' ,'error']);
        $this->server = $server;
    }

    public function shutdown(): void
    {
        if ($this->server) {
            $this->server->close();
            $this->server = null;
        }

        $this->removeOrphanedSocketFile();
    }

    protected function removeOrphanedSocketFile(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
