<?php

namespace IMEdge\Protocol\NetString;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableStream;
use Closure;
use IteratorAggregate;

/**
 * @implements \IteratorAggregate<string>
 */
class NetStringConnection implements NetStringReaderInterface, ReadableStream, WritableStream, IteratorAggregate
{
    use NetStringReaderImplementation;
    use NetStringWriterImplementation;

    public function __construct(
        protected readonly ReadableStream $in,
        protected readonly WritableStream $out,
    ) {
        $this->out->onClose($this->closeIn(...));
    }

    public function isClosed(): bool
    {
        return $this->in->isClosed();
    }

    public function onClose(Closure $onClose): void
    {
        $this->in->onClose($onClose);
    }

    public function close(): void
    {
        $this->out->close();
        $this->closeIn();
    }

    protected function closeIn(): void
    {
        $this->in->close();

        // We might want to complain?
        $this->buffer = '';
        $this->bufferLength = 0;
        $this->bufferOffset = 0;
        $this->expectedLength = null;
    }
}
