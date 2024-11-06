<?php

namespace IMEdge\Protocol\NTP;

// 3-bit version number that indicates the version of NTP. The latest version is version 4
enum NtpVersion: int
{
    case V1 = 1;
    case V2 = 2;
    case V3 = 3;
    case V4 = 4;

    public static function parse(string $byte1): NtpVersion
    {
        $unpacked = unpack('C', $byte1 & "\x38");
        if ($unpacked === false) {
            throw new \RuntimeException('Failed to unpack byte 1');
        }

        return NtpVersion::from($unpacked[1] >> 3);
    }
}
