<?php

namespace IcingaFeature\Snmp\Discovery;

use RuntimeException;

class IpListParser
{
    public static function parse(string $string): array
    {
        $lines = preg_split('/\r?\n/', $string, -1, PREG_SPLIT_NO_EMPTY);
        $ips = [];
        $i = 0;
        while (array_key_exists($i, $lines)) {
            $line = $lines[$i];
            $i++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($line[0] === ';') {
                continue;
            }
            if (str_contains($line, '-')) {
                array_push($lines, ...self::explodeIps($line));
            } else {
                $ips[] = $line;
            }
        }

        return $ips;
    }

    protected static function explodeIps($ip): array
    {
        $ips = [];
        if (!preg_match('/^(.*?)(\d+)-(\d+)(.*?)$/', $ip, $match)) {
            throw new RuntimeException("Invalid IP range: $ip");
        }
        // printf("Range from %s to %s\n", $match[2], $match[3]);

        foreach (range((int) $match[2], (int) $match[3]) as $part) {
            $ips[] = $match[1] . $part . $match[4];
        }

        return $ips;
    }
}
