<?php

namespace IMEdge\CertificateStore\ClientStore;

use IMEdge\CertificateStore\CertificateHelper;
use IMEdge\CertificateStore\DiskBased\DirectoryHelper;
use Sop\CryptoTypes\Asymmetric\PrivateKey;
use Sop\X509\Certificate\Certificate;

class ClientSslStoreDirectory implements ClientSslStoreInterface
{
    use DirectoryHelper;

    protected const DIRECTORIES = ['archive', 'live', 'certs', 'private', 'combined']; // TODO: check
    // serial
    // inventory.txt
    protected const PATH_CA_CERTIFICATE = 'CA.pem';

    protected ?Certificate $cert = null;
    protected ?PrivateKey $key = null;

    /**
    [x] A private key: private/<certname>.pem
    [x] A signed certificate: certs/<certname>.pem
    [x] A copy of the CA certificate: certs/CA.pem -> /CA.pem
    A copy of the certificate revocation list (CRL): crl.pem
    A copy of its sent CSR: certificate_requests/<certname>.pem
     */

    public function __construct(string $basedir)
    {
        $this->initializeDirectoryStructure($basedir, self::DIRECTORIES);
    }

    public function hasCertificate(string $certName): bool
    {
        return $this->hasFile($this->getRelativeCertificatePathByName($certName));
    }

    public function readCertificate(string $certName): ?Certificate
    {
        if ($pem = $this->readPEM($this->getRelativeCertificatePathByName($certName))) {
            return Certificate::fromPEM($pem);
        }

        return null;
    }

    public function store(Certificate $certificate, PrivateKey $privateKey/*, ?Certificate $ca = null*/): void
    {
        $this->writeFile($this->getRelativeCertificatePath($certificate), $certificate->toPEM());
        /* $this->writeFile(
            $this->getRelativeCombinedCertificatePath($certificate),
            ($ca ? $ca->toPEM() . "\n" : '') . $certificate->toPEM()
        );
        */
        $this->writeFile(
            $this->getRelativePrivateKeyPath($certificate),
            $privateKey->toPEM()
        );
    }

    public function writeCertificate(Certificate $certificate): void
    {
        $this->writeFile($this->getRelativeCertificatePath($certificate), $certificate->toPEM());
    }

    public function readPrivateKey(string $certName): ?PrivateKey
    {
        if ($pem = $this->readPEM($this->getRelativePrivateKeyPathByName($certName))) {
            return PrivateKey::fromPEM($pem);
        }

        return null;
    }

    public function writePrivateKey(string $certName, PrivateKey $privateKey): void
    {
        $this->writeFile($this->getRelativePrivateKeyPathByName($certName), $privateKey->toPEM());
    }

    public function readCaCertificate(): ?Certificate
    {
        if ($pem = $this->readPEM($this->getRelativeCaCertificatePath())) {
            return Certificate::fromPEM($pem);
        }

        return null;
    }

    public function writeCaCertificate(Certificate $certificate): void
    {
        $this->writeFile($this->getRelativeCaCertificatePath(), $certificate->toPEM());
    }

    public function getCertificatePath(string $certName): string
    {
        return $this->realpath($this->getRelativeCertificatePathByName($certName));
    }

    public function getCombinedCertificatePath(string $certName): string
    {
        return $this->realpath($this->getRelativeCombinedCertificatePathByName($certName));
    }

    public function getPrivateKeyPath(string $certName): string
    {
        return $this->realpath($this->getRelativePrivateKeyPathByName($certName));
    }

    public function getCaCertificatePath(): string
    {
        return $this->realpath($this->getRelativeCaCertificatePath());
    }

    /* Do we need this?
    private function getAbsolutePathToCombinedFileByName(string $certName): string
    {
        return self::getRelativeCertificatePathByName($certName);
    }
    */

    protected function getRelativeCaCertificatePath(): string
    {
        return self::PATH_CA_CERTIFICATE;
    }

    protected function getRelativeCertificatePath(Certificate $certificate): string
    {
        return self::getRelativeCertificatePathByName(CertificateHelper::getSubjectName($certificate));
    }

    protected function getRelativeCombinedCertificatePath(Certificate $certificate): string
    {
        return self::getRelativeCombinedCertificatePathByName(CertificateHelper::getSubjectName($certificate));
    }

    protected function getRelativePrivateKeyPath(Certificate $certificate): string
    {
        return self::getRelativePrivateKeyPathByName(CertificateHelper::getSubjectName($certificate));
    }

    protected function getRelativePrivateKeyPathByName(string $certName): string
    {
        $certName = self::replaceUnsafeCharacters($certName);
        return "private/$certName.pem";
    }

    protected function getRelativeCertificatePathByName(string $certName): string
    {
        $certName = self::replaceUnsafeCharacters($certName);
        return "certs/$certName.pem";
    }

    protected function getRelativeCombinedCertificatePathByName(string $certName): string
    {
        $certName = self::replaceUnsafeCharacters($certName);
        return "combined/$certName.pem";
    }
/*
 // Obsolete?
    public function readPrivateKeyPEM(): ?PEM
    {
        $string = $this->readFile(self::PATH_CA_PRIVATE_KEY);
        if ($string === null) {
            return null;
        }

        return PEM::fromString($string);
    }

    public function writePrivateKeyPEM(PEM $pem): void
    {
        $this->writeFile(self::PATH_CA_PRIVATE_KEY, $pem->string(), 0600);
    }
*/
}
