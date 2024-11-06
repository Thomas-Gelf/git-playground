<?php

namespace IMEdge\Snmp;

use InvalidArgumentException;

enum SnmpSecurityLevel: string
{
    case NO_AUTH_NO_PRIV  = 'noAuthNoPriv';
    case AUTH_NO_PRIV = 'authNoPriv';
    case AUTH_PRIV  = 'authPriv';

    public function wantsAuthentication(): bool
    {
        return $this !== SnmpSecurityLevel::NO_AUTH_NO_PRIV;
    }

    public function wantsEncryption(): bool
    {
        return $this === SnmpSecurityLevel::AUTH_PRIV;
    }

    public function toBinary(): string
    {
        return match ($this) {
            self::NO_AUTH_NO_PRIV => "\x00",
            self::AUTH_NO_PRIV => "\x01",
            self::AUTH_PRIV => "\x11",
        };
    }

    public static function fromBinaryFlag(string $binary): SnmpSecurityLevel
    {
        return match ($binary) {
            "\x00" => SnmpSecurityLevel::NO_AUTH_NO_PRIV,
            "\x01" => SnmpSecurityLevel::AUTH_NO_PRIV,
            "\x11" => SnmpSecurityLevel::AUTH_PRIV,
            default => throw new InvalidArgumentException(sprintf(
                "'%s' is not a valid SNMPv3 security level",
                $binary
            )),
        };
    }
}
