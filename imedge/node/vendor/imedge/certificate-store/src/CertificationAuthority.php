<?php

namespace IMEdge\CertificateStore;

use IMEdge\CertificateStore\CaStore\CaStoreInterface;
use IMEdge\CertificateStore\Generator\KeyGenerator;
use IMEdge\CertificateStore\Generator\SignatureAlgorithm;
use IMEdge\CertificateStore\Inventory\InventoryInterface;
use IMEdge\CertificateStore\Passphrase\PassphraseProvider;
use InvalidArgumentException;
use RuntimeException;
use Sop\CryptoEncoding\PEM;
use Sop\CryptoTypes\Asymmetric\PrivateKey;
use Sop\CryptoTypes\Asymmetric\PublicKey;
use Sop\X501\ASN1\Name;
use Sop\X509\Certificate\Certificate;
use Sop\X509\Certificate\Extension\BasicConstraintsExtension;
use Sop\X509\Certificate\Extension\ExtendedKeyUsageExtension;
use Sop\X509\Certificate\Extension\Extension;
use Sop\X509\Certificate\Extension\KeyUsageExtension;
use Sop\X509\Certificate\Extension\SubjectAlternativeNameExtension;
use Sop\X509\Certificate\Extension\SubjectKeyIdentifierExtension;
use Sop\X509\Certificate\Extensions;
use Sop\X509\Certificate\TBSCertificate;
use Sop\X509\Certificate\Validity;
use Sop\X509\CertificationRequest\CertificationRequest;
use Sop\X509\GeneralName\DNSName;
use Sop\X509\GeneralName\GeneralNames;

class CertificationAuthority
{
    protected const CA_EXPIRATION = 'now + 15 years';
    protected const DEFAULT_EXPIRATION = 'now + 3 month';
    protected const DEFAULT_SIGNATURE_ALGO = 'sha256';

    protected CaStoreInterface $store;
    protected PrivateKey $key;
    protected Certificate $certificate;
    protected ?InventoryInterface $inventory = null;
    protected ?PassphraseProvider $passphraseProvider = null;

    public function __construct(
        string $name,
        CaStoreInterface $store,
        InventoryInterface $inventory = null,
        ?PassphraseProvider $passphraseProvider = null
    ) {
        $this->store = $store;
        $this->inventory = $inventory;
        $this->passphraseProvider = $passphraseProvider;
        $this->key = $this->requireKey();
        $this->certificate = $this->requireCaCertificate($name);
    }

    public function getCertificate(): Certificate
    {
        return $this->certificate;
    }

    public function getPublicKey(): PublicKey
    {
        return $this->key->publicKey();
    }

    public function sign(CertificationRequest $csr, string $expiration = self::DEFAULT_EXPIRATION): Certificate
    {
        if (!$csr->verify()) {
            throw new InvalidArgumentException('Failed to verify certification request signature');
        }
        $algorithm = SignatureAlgorithm::getAlgorithm(self::DEFAULT_SIGNATURE_ALGO, $this->key);
        $tbs = (TBSCertificate::fromCSR($csr))
            ->withValidity(Validity::fromStrings('now', $expiration))
            // We override requested extensions, that's why we do not use ->withAdditionalExtensions()
            ->withExtensions(new Extensions(...self::getCertificateExtensions($csr)))
            ->withIssuerCertificate($this->certificate);

        $certificate = $this->applyNextSerial($tbs)->sign($algorithm, $this->key->privateKeyInfo());

        if ($this->inventory) {
            $this->inventory->addCertificate($certificate);
        }
        $this->store->storeSignedCertificate($certificate, $csr);

        return $certificate;
    }

    protected function createSelfSignedCaCertificate(string $name, PrivateKey $key, string $validity): Certificate
    {
        $privInfo = $key->privateKeyInfo();
        $pubInfo = $privInfo->publicKeyInfo();
        $name = Name::fromString("cn=$name");
        $validity = Validity::fromStrings('now - 1 minute', $validity);
        $algorithm = SignatureAlgorithm::getAlgorithm(self::DEFAULT_SIGNATURE_ALGO, $this->key);
        $tbs = (new TBSCertificate($name, $pubInfo, $name, $validity))
            ->withAdditionalExtensions(
                new BasicConstraintsExtension(true, true),
                new SubjectKeyIdentifierExtension(false, $pubInfo->keyIdentifier()),
                new KeyUsageExtension(true, KeyUsageExtension::KEY_CERT_SIGN | KeyUsageExtension::CRL_SIGN)
            );
        $certificate = $this->applyNextSerial($tbs)->sign($algorithm, $privInfo);
        $this->store->writeCaCertificate($certificate);
        if ($this->inventory) {
            $this->inventory->addCertificate($certificate);
        }

        return $certificate;
    }

    protected function applyNextSerial(TBSCertificate $tbsCertificate): TBSCertificate
    {
        if ($this->inventory) {
            return $tbsCertificate->withSerialNumber($this->inventory->getNextSerial());
        }

        return $tbsCertificate->withRandomSerialNumber();
    }

    protected function requireCaCertificate(string $name): Certificate
    {
        if ($certificate = $this->store->readCaCertificate()) {
            self::assertLoadedCertificateNameIs($certificate, $name);
            return $certificate;
        }

        return $this->createSelfSignedCaCertificate($name, $this->key, self::CA_EXPIRATION);
    }

    protected function requireKey(): PrivateKey
    {
        $store = $this->store;
        if ($pem = $store->readPrivateKeyPEM()) {
            $key = $this->unsealPrivateKey($pem);
        } else {
            $key = KeyGenerator::generate(4096);
            $store->writePrivateKeyPEM($this->sealPrivateKey($key));
        }

        return $key;
    }

    protected function unsealPrivateKey(PEM $pem): PrivateKey
    {
        if ($this->passphraseProvider !== null) {
            $key = openssl_pkey_get_private($pem->string(), $this->passphraseProvider->getPhrase());
            if (!$key) {
                throw new RuntimeException('Failed to read CA private key');
            }
            $string = null;
            if (! openssl_pkey_export($key, $string)) {
                throw new RuntimeException('Failed to export private key');
            }

            $pem = PEM::fromString($string);
        }

        return PrivateKey::fromPEM($pem);
    }

    protected function sealPrivateKey(PrivateKey $key): PEM
    {
        if ($this->passphraseProvider) {
            $key = openssl_pkey_get_private($key->toPEM()->string());
            if (!$key) {
                throw new RuntimeException('Failed to get CA private key when preparing for encrypted storage');
            }

            $string = null;
            if (! openssl_pkey_export($key, $string, $this->passphraseProvider->getPhrase())) {
                throw new RuntimeException('Failed to export encrypted CA private key');
            }

            return PEM::fromString($string);
        }

        return $key->toPEM();
    }

    /**
     * @return Extension[]
     */
    protected static function getCaExtensions(): array
    {
        return [
            new BasicConstraintsExtension(true, true),
            new KeyUsageExtension(true, KeyUsageExtension::DIGITAL_SIGNATURE | KeyUsageExtension::KEY_CERT_SIGN)
        ];
    }

    /**
     * @return Extension[]
     */
    protected static function getCertificateExtensions(CertificationRequest $csr): array
    {
        $info = $csr->certificationRequestInfo();
        return [
            new BasicConstraintsExtension(true, false),
            new KeyUsageExtension(true, KeyUsageExtension::DIGITAL_SIGNATURE | KeyUsageExtension::KEY_ENCIPHERMENT),
            new ExtendedKeyUsageExtension(
                false,
                ExtendedKeyUsageExtension::OID_SERVER_AUTH,
                ExtendedKeyUsageExtension::OID_CLIENT_AUTH
            ),
            new SubjectKeyIdentifierExtension(false, $info->subjectPKInfo()->keyIdentifier()),
            new SubjectAlternativeNameExtension(false, new GeneralNames(new DNSName(
                $info->subject()->firstValueOf('cn')->stringValue()
            ))),
        ];
    }

    protected static function assertLoadedCertificateNameIs(Certificate $certificate, string $name): void
    {
        $loadedName = $certificate->tbsCertificate()->subject()->firstValueOf('cn')->stringValue();
        if ($name !== $loadedName) {
            throw new RuntimeException("Loaded certificate '$loadedName' does not match expected name '$name'");
        }
    }
}
