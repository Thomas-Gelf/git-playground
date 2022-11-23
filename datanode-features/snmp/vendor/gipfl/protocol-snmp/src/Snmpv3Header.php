<?php

namespace gipfl\Protocol\Snmp;

use InvalidArgumentException;
use Sop\ASN1\Type\Constructed\Sequence;
use Sop\ASN1\Type\Primitive\Integer;
use Sop\ASN1\Type\Primitive\OctetString;

use function strlen;

class Snmpv3Header
{
    protected const REPORTABLE_FLAG = "\x04";

    final public function __construct(
        public readonly int $messageId,   // 0..2147483647
        public readonly int $maxSize = 65507,     // 484..2147483647 -> 65507?
        /**
         * msgSecurityModel
         *
         * The v3MP supports the concurrent existence of multiple Security
         * Models to provide security services for SNMPv3 messages.  The
         * msgSecurityModel field in an SNMPv3 Message identifies which Security
         * Model was used by the sender to generate the message and therefore
         * which securityModel MUST be used by the receiver to perform security
         * processing for the message.  The mapping to the appropriate
         * securityModel implementation within an SNMP engine is accomplished in
         * an implementation-dependent manner.
         */
        public readonly SnmpSecurityLevel $securityFlags = SnmpSecurityLevel::NO_AUTH_NO_PRIV,
        /**
         * see https://tools.ietf.org/html/rfc3412#section-6.4
         *
         * Whether a Report PDU MUST be sent.  It is only used in cases where the
         * PDU portion of a message cannot be decoded, due to, for example, an
         * incorrect encryption key. If the PDU can be decoded, the PDU type forms
         * the basis for decisions on sending Report PDUs.
         */
        public readonly bool $reportableFlag = false,
        public readonly SecurityModel $securityModel = SecurityModel::USM,
    ) {
    }

    public function assertValidSecurityFlags(string $flags): void
    {
        // authFlag = false & privFlag = false => noAuthNoPriv
        // authFlag = false & privFlag = true => invalid combination
        // authFlag = true & privFlag = true => authNoPriv
        // authFlag = true & privFlag = true => authPriv
        if (($flags & "\x11") === "\x10") {
            throw new InvalidArgumentException(
                'Invalid combination, priv without auth message flag'
            );
        }
    }

    public function toASN1(): Sequence
    {
        $flags = ($this->reportableFlag ? "\x04" : "\x00") | $this->securityFlags->toBinary();

        return new Sequence(
            new Integer($this->messageId),
            new Integer($this->maxSize),
            new OctetString($flags),
            new Integer($this->securityModel->value)
        );
    }

    public static function fromAsn1(Sequence $sequence): static
    {
        $flags = $sequence->at(2)->asOctetString()->string();
        if (strlen($flags) !== 1) {
            throw new InvalidArgumentException(sprintf(
                "msgFlags MUST be exactly one byte long, got %d",
                strlen($flags)
            ));
        }

        return new static(
            $sequence->at(0)->asInteger()->intNumber(),
            $sequence->at(1)->asInteger()->intNumber(),
            SnmpSecurityLevel::fromBinaryFlag($flags & "\x03"),
            ($flags & "\x04") === "\x04",
            SecurityModel::from($sequence->at(3)->asInteger()->intNumber()),
        );
        // from rfc3412#page-19:
        // msgID      INTEGER (0..2147483647),
        // msgMaxSize INTEGER (484..2147483647),
        // msgFlags   OCTET STRING (SIZE(1)),
        // --  .... ...1   authFlag
        // --  .... ..1.   privFlag
        // --  .... .1..   reportableFlag
        // --              Please observe:
        // --  .... ..00   is OK, means noAuthNoPriv
        // --  .... ..01   is OK, means authNoPriv
        // --  .... ..10   reserved, MUST NOT be used.
        // --  .... ..11   is OK, means authPriv
        // msgSecurityModel INTEGER (1..2147483647)
    }
}
