<?php

namespace IMEdge\Protocol\NTP;

class NtpReferenceIdentifierInfo
{
    public static function describe(NtpHeader $packet, bool $isIpv4 = true): string
    {
        $id = $packet->referenceIdentifier;
        if ($packet->stratum === NtpStratum::STRATUM1) {
            if ($source = NtpStratumOneClockSource::tryFrom($id)) {
                return $source->value . ': ' . $source->getDescription();
            }

            return sprintf('%s: unknown Stratum 1 reference identifier', $id);
        } elseif ($packet->stratum === NtpStratum::STRATUM_INVALID) {
            if ($kod = KissOfDeath::tryFrom($id)) {
                return $kod->value . ': ' . $kod->getDescription();
            }

            return sprintf("%s: unknown Kiss'o'death reference identifier", $id);
        } elseif ($isIpv4) {
            $ip = inet_ntop($id);
            if ($ip === false) {
                return  '0x' . bin2hex($id);
            }

            return $ip;
        } else {
            // For IPv6 and OSI secondary servers, the value is the first 32 bits of
            // the MD5 hash of the IPv6 or NSAP address of the synchronization source.
            return '0x' . bin2hex($id);
        }
    }
}
