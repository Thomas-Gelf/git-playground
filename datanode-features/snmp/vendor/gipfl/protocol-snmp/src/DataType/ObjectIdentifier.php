<?php

namespace gipfl\Protocol\Snmp\DataType;

use Sop\ASN1\Element;
use Sop\ASN1\Type\UnspecifiedType;
use Sop\ASN1\Type\Primitive\ObjectIdentifier as AsnType;

class ObjectIdentifier extends DataType
{
    public const TAG = Element::TYPE_OBJECT_IDENTIFIER;
    protected int $tag = self::TAG;

    public static function fromString(string $oid): ObjectIdentifier
    {
        return new ObjectIdentifier($oid);
    }

    public static function fromASN1(UnspecifiedType $element): ObjectIdentifier
    {
        return new ObjectIdentifier($element->asObjectIdentifier()->oid());
    }

    public function jsonSerialize(): array
    {
        return [
            'type'  => 'oid',
            'value' => $this->getReadableValue(),
        ];
    }

    public function toASN1(): Element
    {
        return new AsnType(AsnTypeHelper::wantString($this->rawValue));
    }
}
