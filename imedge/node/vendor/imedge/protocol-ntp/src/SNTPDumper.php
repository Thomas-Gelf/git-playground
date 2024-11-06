<?php

namespace IMEdge\Protocol\NTP;

use DateTimeZone;

class SNTPDumper
{
    public static function dump(SNTPResponse $sntp): void
    {
        $header = $sntp->response;
        $timeFormat = 'Y-m-d H:i:s.u';
        $timezone = new DateTimeZone(date_default_timezone_get());
        $tRef = $header->referenceTimestamp?->toDateTime($timezone);
        $tOriginate = $header->originateTimestamp?->toDateTime($timezone);
        $t1 = $sntp->request->transmitTimestamp?->toDateTime($timezone); // equals originate
        $t2 = $header->receiveTimestamp?->toDateTime($timezone);
        $t3 = $header->transmitTimestamp?->toDateTime($timezone);
        $t4 = $sntp->receiveTime;
        $binaryIp = inet_pton($sntp->ipAddress);
        if ($binaryIp === false) {
            throw new \RuntimeException(sprintf('%s is not a valid IP address', $sntp->ipAddress));
        }
        $isIpv6 = strlen($binaryIp) === 16;
        $fields = [
            'Server'      => sprintf($isIpv6 ? '[%s]:%s' : '%s:%s', $sntp->ipAddress, $sntp->ntpPort),
            'NTP Version' => $header->version->value,
            'Association Mode' => $header->ntpMode->name,
            'Leap Indicator' => $header->leapIndicator->name,
            'Stratum' => $header->stratum?->value,
            'Poll Interval' => $header->pollInterval,
            'Precision' => sprintf('%d (%.6fms)', $header->precision, 2 ** $header->precision * 1_000),
            'Root Delay' => $header->rootDelay?->toSeconds(),
            'Root Dispersion' => $header->rootDispersion?->toSeconds(),
            'Reference Identifier' => NtpReferenceIdentifierInfo::describe($header, !$isIpv6),
            'Reference Timestamp' => $tRef?->format($timeFormat),
            'Originate Timestamp (T1, Client)' => $tOriginate?->format($timeFormat)
                . ($t1 ? sprintf(' (%s)', $t1->format($timeFormat)) : ''),
            'Receive Timestamp   (T2, Server)' => $t2?->format($timeFormat),
            'Transmit Timestamp  (T3, Server)' => $t3?->format($timeFormat),
            'Receive Timestamp   (T4, Client)' => $t4->format($timeFormat),
            'Delay' => $sntp->delay ? sprintf('%.3fms', $sntp->delay * 1000) : null,
            'Offset' => $sntp->offset ? sprintf('%.3fms', $sntp->offset * 1000) : null,
            // T4
        ];

        self::dumpFields($fields, $sntp->ipAddress);
    }

    /**
     * @param array<string, ?scalar> $fields
     */
    protected static function dumpFields(array $fields, string $ip): void
    {
        $title = sprintf('SNTP response from %s', $ip);
        echo "$title\n";
        echo str_repeat('-', strlen($title)) . "\n";
        $longestKey = max(array_map(strlen(...), array_keys($fields)));
        foreach ($fields as $key => $value) {
            printf("%s: %s\n", str_pad($key, $longestKey + 1), $value ?? '-');
        }
        echo "\n";
    }
}
