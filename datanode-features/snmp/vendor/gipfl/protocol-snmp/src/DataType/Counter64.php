<?php

namespace gipfl\Protocol\Snmp\DataType;

use InvalidArgumentException;
use Sop\ASN1\Element;
use Sop\ASN1\Type\Primitive\Integer;
use Sop\ASN1\Type\Tagged\ApplicationType;
use Sop\ASN1\Type\UnspecifiedType;
use Sop\ASN1\Util\BigInt;

class Counter64 extends DataType
{
    public const TAG = self::COUNTER_64;
    protected int $tag = self::TAG;

    final protected function __construct(ApplicationType $app)
    {
        // var_export($app->asImplicit(Element::TYPE_INTEGER, static::TAG)->asInteger());
        $value = $app->asImplicit(Element::TYPE_INTEGER, static::TAG)->asInteger()->number();
        $bigInt = new BigInt($value);
        $bigInt = BigInt::fromUnsignedOctets($bigInt->signedOctets());
        // Hint: 2^64-1 gets converted to float with PHP. For performance reason, doing a rough check first
        $obj = $bigInt->gmpObj();
        if ((gmp_sign($obj) === -1)
            || (gmp_cmp($obj, '18446744073709551615') === 1)
        ) {
            throw new InvalidArgumentException(sprintf(
                '%s is not a valid Counter64',
                var_export($value, 1)
            ));
        }
        parent::__construct($obj);
    }

    public static function fromASN1(UnspecifiedType $element): DataType|static
    {
        return new static($element->asApplication());
    }

    public function toASN1(): Element
    {
        return new Integer(AsnTypeHelper::wantGmpIntString($this->rawValue));
    }

    public function jsonSerialize(): array
    {
        return [
            'type'  => self::TYPE_TO_NAME_MAP[static::TAG],
            'value' => gmp_strval($this->rawValue, 10),
        ];
    }
}
