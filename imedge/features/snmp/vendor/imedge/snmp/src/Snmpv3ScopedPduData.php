<?php

namespace IMEdge\Snmp;

use Sop\ASN1\Type\TaggedType;

class Snmpv3ScopedPduData
{
    public function __construct(
        public Pdu $pdu,
    ) {
    }

    public static function fromAsn1(TaggedType $tagged): static
    {
        // ScopedPduData ::= CHOICE {
        //   plaintext    ScopedPDU,
        //   encryptedPDU OCTET STRING  -- encrypted scopedPDU value
        // }

        /** @phpstan-ignore-next-line */
        return new Snmpv3ScopedPduData(Pdu::fromASN1($tagged));
    }
}
