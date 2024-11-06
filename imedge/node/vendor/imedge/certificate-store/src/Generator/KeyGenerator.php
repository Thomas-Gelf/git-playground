<?php

namespace IMEdge\CertificateStore\Generator;

use RuntimeException;
use Sop\CryptoEncoding\PEM;
use Sop\CryptoTypes\Asymmetric\PrivateKey;

use function openssl_pkey_export;
use function openssl_pkey_new;

use const OPENSSL_KEYTYPE_RSA;

class KeyGenerator
{
    protected const DEFAULT_DIGEST_ALGORITHM = 'sha256';

    public static function generate(int $bits = 2048, string $digestAlg = self::DEFAULT_DIGEST_ALGORITHM): PrivateKey
    {
        $config = [
            'digest_alg'       => $digestAlg,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => $bits,
        ];
        $key = openssl_pkey_new($config);
        if ($key === false) {
            throw new RuntimeException('Unable to generate private key');
        }
        $str = null;
        if (! openssl_pkey_export($key, $str)) {
            throw new RuntimeException('Failed to export private key');
        }

        return PrivateKey::fromPEM(PEM::fromString($str));
    }
}
