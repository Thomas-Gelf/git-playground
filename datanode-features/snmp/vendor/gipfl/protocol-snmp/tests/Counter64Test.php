<?php

namespace gipfl\Tests\Protocol\Snmp;

use gipfl\Protocol\Snmp\DataType\Counter64;
use gipfl\Protocol\Snmp\DataType\DataType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sop\ASN1\Component\Identifier;
use Sop\ASN1\Type\Primitive\Integer;
use Sop\ASN1\Type\Tagged\ApplicationType;
use Sop\ASN1\Type\UnspecifiedType;

class Counter64Test extends TestCase
{
    public function testRejectsNegativeNumbersError(): void
    {
        // $this->expectException(InvalidArgumentException::class);
        $this->expectException(\RuntimeException::class);
        $counter = DataType::fromASN1(UnspecifiedType::fromDER((new Integer('9223372036854775808'))->toDER()));
        // $counter = DataType::fromASN1(UnspecifiedType::fromDER((new Integer('18446744073709551616'))->toDER()));
    }
}
