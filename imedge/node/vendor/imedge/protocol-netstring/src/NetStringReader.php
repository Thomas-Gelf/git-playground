<?php

namespace IMEdge\Protocol\NetString;

use Amp\ByteStream\ReadableStream;
use Closure;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<string>
 */
class NetStringReader implements NetStringReaderInterface, ReadableStream, IteratorAggregate
{
    use NetStringReaderImplementation;

    public function __construct(
        protected readonly ReadableStream $in,
    ) {
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
        $this->in->close();

        // We might want to complain?
        $this->buffer = '';
        $this->bufferLength = 0;
        $this->bufferOffset = 0;
        $this->expectedLength = null;
    }
}
