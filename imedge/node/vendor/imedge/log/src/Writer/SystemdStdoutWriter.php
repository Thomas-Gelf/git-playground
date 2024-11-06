<?php

namespace IMEdge\Log\Writer;

use Amp\ByteStream\WritableResourceStream;
use Amp\ByteStream\WritableStream;
use IMEdge\Log\LogLevel;
use InvalidArgumentException;

use function sprintf;

class SystemdStdoutWriter implements LogWriter
{
    protected const DEFAULT_FACILITY = LOG_LOCAL0 >> 3;

    protected WritableStream $stdOut;
    protected int $facility = self::DEFAULT_FACILITY;

    public function __construct(?WritableStream $stdOut = null)
    {
        $this->stdOut = $stdOut ?: new WritableResourceStream(STDOUT);
    }

    public function setFacility(int $facility): void
    {
        if ($facility < 0 || $facility > 23) {
            throw new InvalidArgumentException("Facility needs to be between 0 and 23, got $facility");
        }
        $this->facility = $facility;
    }

    public function write(string $level, string $message, array $context = []): void
    {
        $this->stdOut->write(sprintf(
            "<%d>%s\n",
            LogLevel::mapNameToNumeric($level) | ($this->facility << 3),
            $message
        ));
    }
}
