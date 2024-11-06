<?php

namespace IMEdge\Snmp\Usm;

use IMEdge\Snmp\SnmpV3Message;

class MessageAuthenticator
{
    /**
     * RFC 7860, Section  4.1 / 4.2
     */
    protected const TRUNCATE_SECRET_KEY = [
        'md5' => 16,
        'sha1' => 20,
        'sha224' => 28,
        'sha256' => 32,
        'sha384' => 48,
        'sha512' => 64,
    ];

    /**
     * RFC 7860, Section 4.1 / 4.2
     */
    protected const TRUNCATE_OUTPUT = [
        'md5' => 12,
        'sha1' => 12,
        'sha224' => 16,
        'sha256' => 24,
        'sha384' => 32,
        'sha512' => 48,
    ];

    public function authenticateOutgoingMsg(SnmpV3Message $message, string $algo, string $password): SnmpV3Message
    {
        # RFC 3414, Section 11.2. Passwords must be at least 8 characters in length
        if (\strlen($password) < 8) {
            // SnmpAuthenticationException
            throw new \RuntimeException('The authentication password must be at least 8 characters long.');
        }
        $usm = $message->securityParameters;
        if (! $usm instanceof UserBasedSecurityModel) {
            throw new \RuntimeException('USM not supported');
        }
        $engineId = $usm->engineId;
        if ($engineId === '') {
            // SnmpAuthenticationException
            throw new \RuntimeException('The engineId must be set.');
        }

        # RFC 7860, Section 4.2.1. Step 1:
        #     The msgAuthenticationParameters field is set to the serialization
        #     of an OCTET STRING containing N zero octets; it is serialized
        #     according to the rules in [RFC3417].
        $usm->passwordHash = \str_repeat("\x00", self::TRUNCATE_OUTPUT[$algo]);
        # RFC 7860, Section 4.2.1. Step 4:
        #     The msgAuthenticationParameters field is replaced with the MAC
        #     obtained in the previous step.
        $usm->passwordHash = $this->generateHMAC(
            $algo,
            $message,
            $password,
            $engineId
        );

        return $message;
    }

    protected function generateHMAC(string $algo, SnmpV3Message $message, string $password, string $engineId): string
    {
        # RFC 7860, Section 4.2.1. Step 2:
        #     Using the secret authKey of M octets, the HMAC is calculated over
        #     the wholeMsg according to RFC 6234 with hash function H.
        $key = AuthKey::hash($algo, $password, $engineId);
        $hmac = \hash_hmac(
            $algo,
            $message->toBinary(),
            \substr($key, 0, self::TRUNCATE_SECRET_KEY[$algo]),
            true
        );
        $this->throwOnHashError($hmac, $algo);

        # RFC 7860, Section 4.2.1. Step 3:
        #     The N first octets of the above HMAC are taken as the computed
        #     MAC value.
        return \substr($hmac, 0, self::TRUNCATE_OUTPUT[$algo]);
    }

    protected function throwOnHashError(mixed $result, string $algo): void
    {
        if ($result === false) {
            // SnmpAuthenticationException
            throw new \RuntimeException(sprintf(
                'Unable to hash value using using algorithm %s.',
                $algo
            ));
        }
    }
}
