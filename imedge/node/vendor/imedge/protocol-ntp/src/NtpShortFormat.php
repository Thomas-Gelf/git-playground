<?php

namespace IMEdge\Protocol\NTP;

use DateInterval;

class NtpShortFormat
{
    public function __construct(
        public readonly int $seconds,
        public readonly int $fraction = 0
    ) {
    }

    public function toInterval(): DateInterval
    {
        $interval = DateInterval::createFromDateString(sprintf(
            '%d seconds %d microseconds',
            $this->seconds,
            Util::shortFractionToMicroSeconds($this->fraction)
        ));
        if ($interval === false) {
            throw new \RuntimeException('Failed to create DateInterval from NtpShortFormat');
        }

        return $interval;
    }

    public function toSeconds(): float
    {
        return $this->seconds + (Util::shortFractionToMicroSeconds($this->fraction) / 1_000_000);
    }

    public function __toString(): string
    {
        return pack('n', $this->seconds) . pack('n', $this->fraction);
    }
}
