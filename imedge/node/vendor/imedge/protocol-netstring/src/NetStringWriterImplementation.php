<?php

namespace IMEdge\Protocol\NetString;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\WritableStream;

use function strlen;

/**
 * @internal
 */
trait NetStringWriterImplementation
{
    public function __construct(
        protected readonly WritableStream $out,
    ) {
    }

    public function close(): void
    {
        $this->out->close();
    }

    /**
     * @param string $data
     * @return void
     * @throws ClosedException
     * @throws StreamException
     */
    public function write(string $data): void
    {
        $this->out->write(strlen($data) . ':' . $data . ',');
    }

    public function end(): void
    {
        $this->out->end();
    }

    public function isWritable(): bool
    {
        return $this->out->isWritable();
    }
}
