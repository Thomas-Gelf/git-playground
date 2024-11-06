<?php

namespace IMEdge\Protocol\NTP;

// 2-bit leap indicator. When set to 11, it warns of an alarm condition (clock unsynchronized)
// when set to any other value, this is not to be processed by NTP
enum NtpLeapIndicator: int
{
    case NO_WARNING = 0;
    case LAST_MINUTE_HAS_61_SECONDS = 1;
    case LAST_MINUTE_HAS_59_SECONDS = 2;
    case ALARM_CONDITION = 3; // Clock not synchronized

    public static function parse(string $byte1): NtpLeapIndicator
    {
        $unpacked = unpack('C', $byte1 & "\xC0");
        if ($unpacked === false) {
            throw new \RuntimeException('Failed to unpack byte 1');
        }

        return NtpLeapIndicator::from($unpacked[1] >> 6);
    }
}
