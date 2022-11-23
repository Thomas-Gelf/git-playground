<?php

namespace gipfl\CertificateStore\ClientStore;

use gipfl\CertificateStore\CertificateHelper;
use gipfl\CertificateStore\DiskBased\DirectoryHelper;
use Sop\CryptoEncoding\PEM;
use Sop\CryptoTypes\Asymmetric\PrivateKey;
use Sop\X509\Certificate\Certificate;

class ClientSslStoreDirectory
{
    use DirectoryHelper;

    protected const DIRECTORIES = ['archive', 'live'];
    // serial
    // inventory.txt
    protected const PATH_CA_CERTIFICATE = 'CA.pem';

    protected ?Certificate $cert = null;
    protected ?PrivateKey $key = null;

    public function __construct(string $basedir)
    {
        $this->initializeDirectoryStructure($basedir, self::DIRECTORIES);
    }

    /**
    A private key: private/<certname>.pem
    A signed certificate: certs/<certname>.pem
    A copy of the CA certificate: certs/CA.pem
    A copy of the certificate revocation list (CRL): crl.pem
    A copy of its sent CSR: certificate_requests/<certname>.pem
    */


    public function readCaCertificate(): ?Certificate
    {
        // TODO: Implement readCaCertificate() method.
    }

    public function writeCaCertificate(Certificate $certificate): void
    {
        // TODO: Implement writeCaCertificate() method.
    }

    public function writeCertificate(Certificate $certificate, PrivateKey $privateKey): void
    {
        $this->writeFile($this->getCertificatePath($certificate), $certificate->toPEM());
        $this->writeFile($this->getPrivateKeyPath($certificate), $privateKey->toPEM());
    }

    public function getAbsoluteCertificatePathByName(string $certName): string
    {
        return $this->realpath($this->getCertificatePathByName($certName));
    }

    public function getAbsolutePrivateKeyPathByName(string $certName): string
    {
        return $this->realpath($this->getPrivateKeyPathByName($certName));
    }

    public function getAbsolutePathToCombinedFileByName(string $certName): string
    {
        return self::getCertificatePathByName();
    }


    protected function getCertificatePath(Certificate $certificate): string
    {
        return self::getCertificatePathByName(CertificateHelper::getSubjectName($certificate));
    }

    protected function getPrivateKeyPath(Certificate $certificate): string
    {
        return self::getPrivateKeyPathByName(CertificateHelper::getSubjectName($certificate));
    }

    protected function getPrivateKeyPathByName(string $certName): string
    {
        $certName = self::replaceUnsafeCharacters($certName);
        return "$certName.pem";
    }

    protected function getCertificatePathByName(string $certName): string
    {
        $certName = self::replaceUnsafeCharacters($certName);
        return "certs/$certName.pem";
    }

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

    public function readPrivateKeyPEM(): ?PEM
    {
        // TODO: Implement readPrivateKeyPEM() method.
    }

    public function writePrivateKeyPEM(PEM $pem): void
    {
        // TODO: Implement writePrivateKeyPEM() method.
    }
}
