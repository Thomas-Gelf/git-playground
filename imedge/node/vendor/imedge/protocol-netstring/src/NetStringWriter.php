<?php

namespace IMEdge\Protocol\NetString;

use Amp\ByteStream\WritableStream;

class NetStringWriter implements WritableStream
{
    use NetStringWriterImplementation;

    public function isClosed(): bool
    {
        return $this->out->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->out->onClose($onClose);
    }

    public function close(): void
    {
        $this->out->close();
    }
}
