<?php

namespace gipfl\Protocol\Snmp\DataType;

use InvalidArgumentException;
use Sop\ASN1\Element;
use Sop\ASN1\Type\Primitive\NullType;
use Sop\ASN1\Type\Tagged\ImplicitlyTaggedType;
use Sop\ASN1\Type\UnspecifiedType;

/**
 *
 * From RFC2089:
 *
 * - For SNMP GET requests we can get back noSuchObject and noSuchInstance
 * - For SNMP GETNEXT requests we can get back endOfMibView
 * - For SNMP SET requests we cannot get back any exceptions
 *
 *
 * - For SNMP GETBULK requests we can get back endOfMibView, but such a request should only come in as an SNMPv2
 *   request, so we do not have to worry about any mapping onto SNMPv1. If a GETBULK comes in as an SNMPv1 request, it
 *   is treated as an error and the packet is dropped
 */
class DataTypeContextSpecific extends DataType
{
    public const NO_SUCH_OBJECT = 0;
    public const NO_SUCH_INSTANCE = 1;
    public const END_OF_MIB_VIEW = 2;

    /** @var array<int, string> */
    protected static array $errorMessages = [
        self::NO_SUCH_OBJECT   => 'No such object',
        self::NO_SUCH_INSTANCE => 'No such instance',
        self::END_OF_MIB_VIEW  => 'End of MIB view',
    ];

    final protected function __construct(int $rawValue)
    {
        parent::__construct(null);
        $this->tag = $rawValue;
    }

    public function getReadableValue(): string
    {
        return '[ ' . self::$errorMessages[$this->tag] . ' ]';
    }

    public function jsonSerialize(): array
    {
        return [
            'type'  => 'context_specific',
            'value' => $this->tag,
        ];
    }

    public static function noSuchObject(): static
    {
        return new static(self::NO_SUCH_OBJECT);
    }

    public static function noSuchInstance(): static
    {
        return new static(self::NO_SUCH_INSTANCE);
    }

    public static function endOfMibView(): static
    {
        return new static(self::END_OF_MIB_VIEW);
    }

    public static function fromASN1(UnspecifiedType $element): static
    {
        $tag = $element->tag();
        if (isset(static::$errorMessages[$tag])) {
            return new static($tag);
        } else {
            throw new InvalidArgumentException(
                "Unknown context specific data type, tag=$tag"
            );
        }
    }

    public function toASN1(): Element
    {
        return new ImplicitlyTaggedType($this->getTag(), new NullType());
    }
}
