<?php

namespace IMEdge\Protocol\NTP;

/**
 * 8-bit integer that indicates the stratum level of the local clock, with the value ranging from 1 to 16
 * Clock precision decreases from stratum 1 through stratum 16. A stratum 1 clock has the highest precision,
 * and a stratum 16 clock is not synchronized and cannot be used as a reference clock
 */
enum NtpStratum: int
{
    case STRATUM_INVALID = 0;
    case STRATUM1 = 1;
    case STRATUM2 = 2;
    case STRATUM3 = 3;
    case STRATUM4 = 4;
    case STRATUM5 = 5;
    case STRATUM6 = 6;
    case STRATUM7 = 7;
    case STRATUM8 = 8;
    case STRATUM9 = 9;
    case STRATUM10 = 10;
    case STRATUM11 = 11;
    case STRATUM12 = 12;
    case STRATUM13 = 13;
    case STRATUM14 = 14;
    case STRATUM15 = 15;
    case STRATUM16 = 16;
    // 17-255: reserved

    public function toPacket(): string
    {
        return pack('C', $this->value);
    }
}
