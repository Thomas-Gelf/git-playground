<?php

namespace IMEdge\CertificateStore\Generator;

use Sop\CryptoTypes\Asymmetric\PrivateKey;
use Sop\X501\ASN1\Name;
use Sop\X509\Certificate\Extension\SubjectAlternativeNameExtension;
use Sop\X509\Certificate\Extensions;
use Sop\X509\CertificationRequest\CertificationRequest;
use Sop\X509\CertificationRequest\CertificationRequestInfo;
use Sop\X509\GeneralName\DNSName;
use Sop\X509\GeneralName\GeneralNames;

class CsrGenerator
{
    protected const DEFAULT_HASH_ALGORITHM = 'sha256';

    public static function generate(string $name, PrivateKey $key): CertificationRequest
    {
        $subject = Name::fromString("cn=$name");
        $cri = new CertificationRequestInfo($subject, $key->publicKey()->publicKeyInfo());
        $cri = $cri->withExtensionRequest(new Extensions(
            new SubjectAlternativeNameExtension(false, new GeneralNames(new DNSName($name)))
        ));

        return $cri->sign(SignatureAlgorithm::getAlgorithm(
            static::DEFAULT_HASH_ALGORITHM,
            $key
        ), $key->privateKeyInfo());
    }
}
