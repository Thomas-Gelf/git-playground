<?php

namespace IMEdge\Protocol\NetString;

use Amp\ByteStream\StreamException;
use Generator;

interface NetStringReaderInterface
{
    /**
     * @throws NetStringProtocolError
     * @throws StreamException
     * @return Generator<string>
     */
    public function packets(): Generator;
}
