<?php

namespace IMEdge\CertificateStore\Generator;

use InvalidArgumentException;
use Sop\CryptoTypes\AlgorithmIdentifier\Feature\AsymmetricCryptoAlgorithmIdentifier;
use Sop\CryptoTypes\AlgorithmIdentifier\Feature\SignatureAlgorithmIdentifier;
use Sop\CryptoTypes\AlgorithmIdentifier\Hash\SHA256AlgorithmIdentifier;
use Sop\CryptoTypes\AlgorithmIdentifier\Hash\SHA512AlgorithmIdentifier;
use Sop\CryptoTypes\AlgorithmIdentifier\Signature\SignatureAlgorithmIdentifierFactory;
use Sop\CryptoTypes\Asymmetric\PrivateKey;

class SignatureAlgorithm
{
    protected const SHA256 = 'sha256';
    protected const SHA512 = 'sha512';

    public static function getAlgorithm(string $hashAlgo, PrivateKey $key): SignatureAlgorithmIdentifier
    {
        $hashAlgo = match ($hashAlgo) {
            self::SHA256 => new SHA256AlgorithmIdentifier(),
            self::SHA512 => new SHA512AlgorithmIdentifier(),
            default => throw new InvalidArgumentException("Unknown HASH algorithm $hashAlgo"),
        };
        /** @var AsymmetricCryptoAlgorithmIdentifier $usedAlgo */
        $usedAlgo = $key->privateKeyInfo()->algorithmIdentifier();

        return SignatureAlgorithmIdentifierFactory::algoForAsymmetricCrypto($usedAlgo, $hashAlgo);
    }
}
