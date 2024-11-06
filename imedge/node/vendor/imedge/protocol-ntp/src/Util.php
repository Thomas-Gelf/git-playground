<?php

namespace IMEdge\Protocol\NTP;

use Amp\Dns\DnsRecord;
use DateTime;
use RuntimeException;

use function Amp\Dns\resolve;

class Util
{
    protected const NANO_TO_MILLI = 1_000_000;
    public const NANO_TO_SECONDS = 1_000_000_000;
    protected const FRACTION_PRECISION = 4_294_967_296; // 2 ** 32
    protected const SHORT_FRACTION_PRECISION = 65_535; // 2 ** 16

    /**
     * @return string[]
     */
    public static function getIps(string $server): array
    {
        $binary = inet_pton($server);
        if ($binary !== false) {
            return [$server];
        }

        $records = resolve($server);
        $ips = [];
        foreach ($records as $record) {
            /** @var DnsRecord $record */
            if ($record->getType() !== DnsRecord::A && $record->getType() !== DnsRecord::AAAA) {
                printf("Skipping %s\n", $record->getValue());
                continue;
            }
            // printf("Trying %s\n", $record->getValue());
            $ips[] = $record->getValue();
        }

        return $ips;
    }

    public static function ipIsv6(string $ip): bool
    {
        $binary = inet_pton($ip);
        if ($binary && strlen($binary) === 16) {
            return true;
        }

        return false;
    }

    public static function makeUri(string $ip, int $port): string
    {
        $binaryIp = inet_pton($ip);
        if ($binaryIp === false) {
            throw new RuntimeException('IP address expected, got ' . $ip);
        }
        return strlen($binaryIp) === 4 ? "udp://$ip:$port" : "udp://[$ip]:$port";
    }

    public static function fractionToMicroSeconds(int $fraction): int
    {
        return intval($fraction / self::FRACTION_PRECISION * 1_000_000);
    }

    public static function microSecondsToFraction(int $microseconds): int
    {
        return (int)round($microseconds / 1_000_000 * self::FRACTION_PRECISION);
    }

    public static function shortFractionToMicroSeconds(int $fraction): int
    {
        return (int)round($fraction / self::SHORT_FRACTION_PRECISION * 1_000_000);
    }

    public static function dateTimeMinusDateTime(DateTime $left, DateTime $right): float
    {
        return (float)$left->format('U.u') - (float)$right->format('U.u');
    }

    public static function calculateDelay(DateTime $t1, DateTime $t2, DateTime $t3, DateTime $t4): float
    {
        // (T4 - T1) - (T3 - T2)
        return Util::dateTimeMinusDateTime($t4, $t1) - Util::dateTimeMinusDateTime($t3, $t2);
    }

    public static function calculateOffset(DateTime $t1, DateTime $t2, DateTime $t3, DateTime $t4): float
    {
        // ((T2 - T1) + (T3 - T4)) / 2
        return (
            Util::dateTimeMinusDateTime($t2, $t1)   // client -> server
            + Util::dateTimeMinusDateTime($t3, $t4) // server -> client
        ) / 2;
    }
}
