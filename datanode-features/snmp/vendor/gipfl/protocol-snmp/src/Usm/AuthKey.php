<?php

namespace gipfl\Protocol\Snmp\Usm;

use function floor;
use function hash;
use function str_repeat;
use function strlen;
use function substr;

class AuthKey
{
    protected const WTF_LENGTH = 1048576;

    public static function intermediate(string $algo, string $pass): string
    {
        return hash($algo, self::largeString($pass), true);
    }

    protected static function largeString(string $string): string
    {
        return str_repeat($string, (int) floor(self::WTF_LENGTH / strlen($string)))
            . substr($string, 0, self::WTF_LENGTH % strlen($string));
    }

    public static function hash(string $algo, string $pass, string $engineId): string
    {
        $intermediate = self::intermediate($algo, $pass);
        return hash($algo, $intermediate . $engineId . $intermediate, true);
    }
}
