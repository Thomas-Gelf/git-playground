<?php

namespace IMEdge\Snmp;

use IMEdge\Snmp\Usm\UserBasedSecurityModel;
use Sop\ASN1\Type\Constructed\Sequence;
use Sop\ASN1\Type\Primitive\Integer;
use Sop\ASN1\Type\Primitive\OctetString;

class SnmpV3Message extends SnmpMessage
{
    protected int $version = self::SNMP_V3;

    // TODO: Should we really require a full header, or just some params?
    final public function __construct(
        public readonly Snmpv3Header $header,
        public readonly Snmpv3SecurityParameters $securityParameters, // defined by security model
        public readonly Snmpv3ScopedPdu $scopedPdu, // Or encrypted PDU -> OctetString
    ) {
    }

    public function getPdu(): Pdu
    {
        return $this->scopedPdu->pdu;
    }

    public function toASN1(): Sequence
    {
        return new Sequence(
            new Integer($this->version),
            $this->header->toASN1(),
            new OctetString((string) $this->securityParameters),
            // encrypted: new OctetString($this->scopedPdu->toASN1()->toDER()),
            $this->scopedPdu->toASN1(),
        );
    }

    public static function fromASN1(Sequence $sequence): static
    {
        if ($sequence->count() !== 4) {
            throw new \InvalidArgumentException('An SNMPv3 message must consist of 4 elements');
        }
        // TODO: should we protect this method, allowing us to disable this (redundant) check?
        // if ($sequence->at(0)->asInteger()->intNumber() !== self::SNMP_V3) {
        //    throw new \InvalidArgumentException('This is not an SNMPv3 packet')
        // }

        // RFC 3412, page 18
        // SNMPv3Message ::= SEQUENCE {
        // -- identify the layout of the SNMPv3Message
        // -- this element is in same position as in SNMPv1
        // -- and SNMPv2c, allowing recognition
        // -- the value 3 is used for snmpv3
        // msgVersion INTEGER ( 0 .. 2147483647 ),
        // HeaderData administrative parameters:
        // security model-specific parameters
        // format defined by Security Model:
        // ScopedPduData:
        $header = Snmpv3Header::fromAsn1($sequence->at(1)->asSequence());
        if ($header->securityModel === SecurityModel::USM) {
            $securityModel = UserBasedSecurityModel::fromString($sequence->at(2)->asOctetString()->string());
        } else {
            throw new \InvalidArgumentException('Unsupported security model: ' . $header->securityModel->name);
        }
        return new static(
            $header,
            $securityModel,
            Snmpv3ScopedPdu::fromAsn1($sequence->at(3)->asSequence())
        );
    }
}
