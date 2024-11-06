<?php

namespace IMEdge\CertificateStore;

use InvalidArgumentException;
use RuntimeException;
use Sop\CryptoTypes\AlgorithmIdentifier\Feature\AsymmetricCryptoAlgorithmIdentifier;
use Sop\CryptoTypes\AlgorithmIdentifier\Feature\SignatureAlgorithmIdentifier;
use Sop\CryptoTypes\AlgorithmIdentifier\Hash\SHA256AlgorithmIdentifier;
use Sop\CryptoTypes\AlgorithmIdentifier\Hash\SHA512AlgorithmIdentifier;
use Sop\CryptoTypes\AlgorithmIdentifier\Signature\SignatureAlgorithmIdentifierFactory;
use Sop\CryptoTypes\Asymmetric\PrivateKey;
use Sop\X501\ASN1\Name;
use Sop\X509\Certificate\Certificate;
use Sop\X509\Certificate\Extension\BasicConstraintsExtension;
use Sop\X509\Certificate\Extension\KeyUsageExtension;
use Sop\X509\Certificate\Extension\SubjectAlternativeNameExtension;
use Sop\X509\Certificate\Extensions;
use Sop\X509\Certificate\TBSCertificate;
use Sop\X509\Certificate\Validity;
use Sop\X509\CertificationRequest\CertificationRequest;
use Sop\X509\CertificationRequest\CertificationRequestInfo;
use Sop\X509\GeneralName\DNSName;
use Sop\X509\GeneralName\GeneralNames;

use function hash;

class CertificateHelper
{
    protected const FINGERPRINT_ALGORITHM = 'sha256';

    public static function fingerprint(Certificate|CertificationRequest $certificate): string
    {
        return hash(self::FINGERPRINT_ALGORITHM, $certificate->toDER());
    }

    public static function getSubjectName(Certificate $certificate): string
    {
        return $certificate->tbsCertificate()->subject()->firstValueOf('cn')->stringValue();
    }

    protected static function getSignatureAlgorithm(
        PrivateKey $privateKey,
        string $hashAlgo = 'sha256'
    ): SignatureAlgorithmIdentifier {
        switch ($hashAlgo) {
            case 'sha256':
                $hashAlgo = new SHA256AlgorithmIdentifier();
                break;
            case 'sha512':
                $hashAlgo = new SHA512AlgorithmIdentifier();
                break;
            default:
                throw new InvalidArgumentException("Unknown HASH algorithm $hashAlgo");
        }
        /** @var AsymmetricCryptoAlgorithmIdentifier $usedAlgo */
        $usedAlgo = $privateKey->privateKeyInfo()->algorithmIdentifier();

        return SignatureAlgorithmIdentifierFactory::algoForAsymmetricCrypto(
            $usedAlgo,
            $hashAlgo
        );
    }

    public static function generateCsr(string $cn, PrivateKey $key): CertificationRequest
    {
        $subject = Name::fromString("cn=$cn");
        $cri = new CertificationRequestInfo($subject, $key->publicKey()->publicKeyInfo());
        $alt = new SubjectAlternativeNameExtension(false, new GeneralNames(new DNSName($cn)));
        $cri = $cri->withExtensionRequest(new Extensions($alt));

        return $cri->sign(self::getSignatureAlgorithm($key, 'sha512'), $key->privateKeyInfo());
    }

    public static function createTemporarySelfSigned(string $cn, PrivateKey $privateKey): Certificate
    {
        $csr = self::generateCsr($cn, $privateKey);
        return self::signSelfSigned($csr, $privateKey, 'now + 3 months');
    }

    public static function signSelfSigned(
        CertificationRequest $csr,
        PrivateKey $privateKey,
        string $expiration
    ): Certificate {
        if (!$csr->verify()) {
            throw new InvalidArgumentException('Failed to verify certification request signature');
        }
        $tbsCert = TBSCertificate::fromCSR($csr);
        $tbsCert = $tbsCert->withRandomSerialNumber();
        $tbsCert = $tbsCert->withValidity(Validity::fromStrings('now', $expiration));
        $tbsCert = $tbsCert->withAdditionalExtensions(
            new KeyUsageExtension(
                true,
                KeyUsageExtension::DIGITAL_SIGNATURE | KeyUsageExtension::KEY_ENCIPHERMENT
            ),
            new BasicConstraintsExtension(true, false)
        );
        $algo = self::getSignatureAlgorithm($privateKey, 'sha512');
        $cert = $tbsCert->sign($algo, $privateKey->privateKeyInfo());

        return new Certificate(
            $cert->tbsCertificate()->withIssuerCertificate($cert),
            $algo,
            $csr->signature()
        );
    }
}
