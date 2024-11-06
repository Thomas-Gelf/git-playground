<?php

namespace IMEdge\Snmp;

use IMEdge\Json\JsonSerialization;
use InvalidArgumentException;
use Stringable;

class SocketAddress implements Stringable, JsonSerialization
{
    final public function __construct(
        public string $ip,
        public ?int $port = 0
    ) {
    }

    public static function parse(string $string, ?int $defaultPort = 0): static
    {
        if (str_contains($string, ':')) { // TODO: last :, validate IP, v6!
            return new static(...explode(':', $string));
        }

        return new static($string, $defaultPort);
    }

    public static function detect(SocketAddress|string $address, ?int $defaultPort = 0): static
    {
        if ($address instanceof static) {
            return $address;
        }

        return static::parse($address, $defaultPort);
    }

    public function toUdpUri(): string
    {
        return 'udp://' . $this->__toString();
    }

    public static function fromSerialization($any): SocketAddress
    {
        if ((!is_object($any)) || !is_string($any->ip ?? null)) {
            throw new InvalidArgumentException('Socket Address expected, got ' . var_export($any, true));
        }

        return new SocketAddress(...(array) $any);
    }

    public function jsonSerialize(): object
    {
        return (object) array_filter(get_object_vars($this), fn($v) => $v !== null);
    }

    public function __toString(): string
    {
        return $this->ip . ':' . $this->port;
    }
}
