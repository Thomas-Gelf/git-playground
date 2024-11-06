<?php

namespace IMEdge\Snmp\DataType;

use InvalidArgumentException;
use Sop\ASN1\Element;
use Sop\ASN1\Type\Primitive\Integer;
use Sop\ASN1\Type\UnspecifiedType;

class Unsigned32 extends DataType
{
    public const TAG = self::UNSIGNED_32;
    protected int $tag = self::TAG;

    final public function __construct(int $int)
    {
        if ($int < 0 || $int > 4294967295) {
            throw new InvalidArgumentException(sprintf(
                '%s is not a valid unsigned integer',
                $int
            ));
        }
        parent::__construct($int);
    }

    public static function fromInteger(int $int): static
    {
        return new static($int);
    }

    public function jsonSerialize(): array
    {
        return [
            'type'  => self::TYPE_TO_NAME_MAP[static::TAG],
            'value' => $this->rawValue,
        ];
    }

    public static function fromASN1(UnspecifiedType $element): static
    {
        return new static(
            $element->asApplication()->asImplicit(Element::TYPE_INTEGER, static::TAG)->asInteger()->intNumber()
        );
    }

    public function toASN1(): Element
    {
        return new Integer(AsnTypeHelper::wantGmpIntString($this->rawValue));
    }
}
