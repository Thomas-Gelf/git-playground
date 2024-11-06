<?php

namespace IMEdge\Snmp\DataType;

use GMP;
use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;
use Sop\ASN1\Component\Identifier;
use Sop\ASN1\Element;
use Sop\ASN1\Type\UnspecifiedType;
use Stringable;

abstract class DataType implements JsonSerializable
{
    public const IP_ADDRESS = 0;
    public const COUNTER_32 = 1;
    public const GAUGE_32 = 2;
    public const TIME_TICKS = 3;
    public const OPAQUE = 4;
    public const NSAP_ADDRESS = 5;
    public const COUNTER_64 = 6;
    public const UNSIGNED_32 = 7;

    /*
     * SNMPv2-SMI:
     *
     * -- the "base types" defined here are:
     * --   3 built-in ASN.1 types: INTEGER, OCTET STRING, OBJECT IDENTIFIER
     * --   8 application-defined types: Integer32, IpAddress, Counter32,
     * --              Gauge32, Unsigned32, TimeTicks, Opaque, and Counter64
     *
     * IpAddress ::= [APPLICATION 0] IMPLICIT OCTET STRING (SIZE (4))
     *
     * -- this wraps
     * Counter32 ::= [APPLICATION 1] IMPLICIT INTEGER (0..4294967295)
     *
     * -- this doesn't wrap
     * Gauge32 ::= [APPLICATION 2] IMPLICIT INTEGER (0..4294967295)
     *
     * -- an unsigned 32-bit quantity
     * -- indistinguishable from Gauge32
     * Unsigned32 ::= [APPLICATION 2] IMPLICIT INTEGER (0..4294967295)
     *
     * -- hundredths of seconds since an epoch
     * TimeTicks ::= [APPLICATION 3] IMPLICIT INTEGER (0..4294967295)
     *
     * -- for backward-compatibility only
     * Opaque ::= [APPLICATION 4] IMPLICIT OCTET STRING
     *
     * -- for counters that wrap in less than one hour with only 32 bits
     * Counter64 ::= [APPLICATION 6] IMPLICIT INTEGER (0..18446744073709551615)
     */
    protected const TYPE_TO_NAME_MAP = [
        self::IP_ADDRESS   => 'ip_address',
        self::COUNTER_32   => 'counter32',
        self::GAUGE_32     => 'gauge32',
        self::TIME_TICKS   => 'time_ticks',
        self::OPAQUE       => 'opaque',
        self::NSAP_ADDRESS => 'nsap_address',
        self::COUNTER_64   => 'counter64',
        self::UNSIGNED_32  => 'unsigned32',
    ];

    // From SNMPv2-SMI:
    // ipAddress-value => IpAddress
    // counter-value => Counter32
    // timeticks-value => TimeTicks
    // arbitrary-value => Opaque
    // -> nsap?
    // big-counter-value => Counter64
    // unsigned-integer-value => Unsigned32

    protected const NAME_TO_TYPE_MAP = [
        'ip_address'   => self::IP_ADDRESS,
        'counter32'    => self::COUNTER_32,
        'gauge32'      => self::GAUGE_32,
        'time_ticks'   => self::TIME_TICKS,
        'opaque'       => self::OPAQUE,
        'nsap_address' => self::NSAP_ADDRESS,
        'counter64'    => self::COUNTER_64,
        'unsigned32'   => self::UNSIGNED_32,
    ];

    /** @var mixed TODO: 'mixed' causes problems and asks for too much type checking */
    protected mixed $rawValue;

    protected int $tag;

    protected function __construct(mixed $rawValue)
    {
        $this->rawValue = $rawValue;
    }

    public function getTag(): int
    {
        return $this->tag;
    }

    abstract public function toASN1(): Element;

    /**
     * @return array{'type': string, 'value': mixed}
     */
    abstract public function jsonSerialize(): array;

    public function getReadableValue(): string
    {
        if (is_int($this->rawValue)) {
            return (string)$this->rawValue;
        }
        if (is_string($this->rawValue)) {
            return self::getPrintableString($this->rawValue);
        }
        if ($this->rawValue instanceof GMP) {
            return gmp_strval($this->rawValue);
        }
        if ($this->rawValue instanceof Stringable) {
            return (string) $this->rawValue;
        }

        throw new RuntimeException('Cannot provide readable value for rawValue in ' . get_class($this));
    }

    protected static function getPrintableString(string $string): string
    {
        if (ctype_print($string)) {
            $value = $string;
        } else {
            $value = '';
            foreach (mb_str_split($string) as $char) {
                if (\IntlChar::isprint($char)) {
                    $value .= $char;
                } else {
                    $value .= '\\x' . bin2hex($char);
                }
            }
        }

        return $value;
    }

    public static function fromBinary(string $binary): DataType|static
    {
        return self::fromASN1(UnspecifiedType::fromDER($binary));
    }

    public static function fromASN1(UnspecifiedType $element): DataType|static
    {
        $class = $element->typeClass();

        switch ($element->typeClass()) {
            case Identifier::CLASS_UNIVERSAL:
                return DataTypeUniversal::fromASN1($element);
            case Identifier::CLASS_APPLICATION:
                return DataTypeApplication::fromASN1($element);
            case Identifier::CLASS_CONTEXT_SPECIFIC:
                return DataTypeContextSpecific::fromASN1($element);
            default:
                $className = Identifier::classToName($class);
                throw new InvalidArgumentException(
                    "Unsupported ASN1 class=$className"
                );
        }
    }
}
