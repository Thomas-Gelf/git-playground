<?php

namespace IMEdge\Snmp\Usm;

use IMEdge\Snmp\Snmpv3SecurityParameters;
use Sop\ASN1\Type\Constructed\Sequence;
use Sop\ASN1\Type\Primitive\Integer;
use Sop\ASN1\Type\Primitive\OctetString;

class UserBasedSecurityModel implements Snmpv3SecurityParameters
{
    protected const TIME_WINDOW_SECONDS = 150;

    public function __construct(
        public readonly string $username,
        public readonly string $engineId = '', // ?? -> <MISSING> -->??
        public int $engineBoots = 0,
        public int $engineTime = 0,
        public string $passwordHash = '',
    ) {
    }

    public static function fromString(string $string): UserBasedSecurityModel
    {
        $sequence = Sequence::fromDER($string)->asUnspecified()->asSequence();
        return new UserBasedSecurityModel(
            $sequence->at(3)->asOctetString()->string(),
            $sequence->at(0)->asOctetString()->string(),
            $sequence->at(1)->asInteger()->intNumber(),
            $sequence->at(2)->asInteger()->intNumber(),
            $sequence->at(4)->asOctetString()->string(), // authParams
        );
        // 4 -> authParams
        // 5 -> privParams
    }

    public function __toString(): string
    {
        $sequence = new Sequence(
            new OctetString($this->engineId),
            new Integer($this->engineBoots),
            new Integer($this->engineTime),
            new OctetString($this->username), // 0..32 characters
            new OctetString($this->passwordHash),
            new OctetString(''), // privacyParameters
        );
        return $sequence->toDER();
    }
}
