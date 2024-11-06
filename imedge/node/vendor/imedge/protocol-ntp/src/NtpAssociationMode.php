<?php

namespace IMEdge\Protocol\NTP;

enum NtpAssociationMode: int
{
    // 0 -> reserved
    case SYMMETRIC_ACTIVE = 1;
    case SYMMETRIC_PASSIVE = 2;
    case CLIENT = 3;
    case SERVER = 4;
    case BROADCAST = 5; // or multicast
    case CONTROL_MESSAGE = 6;
    case PRIVATE_USE = 7;

    public static function parse(string $byte1): NtpAssociationMode
    {
        $unpacked = unpack('C', $byte1 & "\x07");
        if ($unpacked === false) {
            throw new \RuntimeException('Failed to unpack byte 1');
        }

        return NtpAssociationMode::from($unpacked[1]);
    }
}
