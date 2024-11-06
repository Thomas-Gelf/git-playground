<?php

namespace IMEdge\Protocol\NTP;

use DateTime;
use DateTimeZone;

class NtpTimestampFormat
{
    public const SECONDS_FROM_1900_TO_EPOCH = 2_208_988_800;

    public function __construct(
        public readonly int $seconds,
        public readonly int $fraction = 0
    ) {
    }

    public static function now(): NtpTimestampFormat
    {
        return self::fromMicroTime(microtime());
    }

    public static function fromMicroTime(string $microtime): NtpTimestampFormat
    {
        $parts = explode(' ', $microtime, 2);
        return new NtpTimestampFormat(
            (int) ((int) $parts[1] + self::SECONDS_FROM_1900_TO_EPOCH),
            Util::microSecondsToFraction((int) substr($parts[0], 2, 6))
        );
    }

    public static function optional(int $seconds = 0, int $fraction = 0): ?NtpTimestampFormat
    {
        if ($seconds === 0) {
            return null;
        }

        return new NtpTimestampFormat($seconds, $fraction);
    }

    public function toDateTime(?DateTimeZone $timeZone = null): DateTime
    {
        $datetime = DateTime::createFromFormat(
            'U.u',
            sprintf(
                '%d.%06d',
                $this->seconds - self::SECONDS_FROM_1900_TO_EPOCH,
                Util::fractionToMicroSeconds($this->fraction)
            )
        );
        if (! $datetime) {
            // Should not happen. In case it does: DateTime::getLastErrors()
            throw new \RuntimeException('Unable to instantiate DateTime object');
        }
        $datetime->setTimezone($timeZone ?? new DateTimeZone('UTC'));

        return $datetime;
    }

    public function __toString(): string
    {
        return pack('N', $this->seconds) . pack('N', $this->fraction);
    }
}
