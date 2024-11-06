<?php

namespace IMEdge\CertificateStore\CaStore;

use IMEdge\CertificateStore\CertificateHelper;
use IMEdge\CertificateStore\DiskBased\DirectoryBasedComponent;
use IMEdge\CertificateStore\DiskBased\DirectoryHelper;
use IMEdge\CertificateStore\DiskBased\FileStore;
use RuntimeException;
use Sop\CryptoEncoding\PEM;
use Sop\CryptoTypes\Asymmetric\PrivateKey;
use Sop\X509\Certificate\Certificate;
use Sop\X509\CertificationRequest\CertificationRequest;

use function explode;
use function glob;
use function strlen;
use function substr;

class CaStoreDirectory implements CaStoreInterface, DirectoryBasedComponent, FileStore
{
    use DirectoryHelper;

    protected const DIRECTORIES = ['signed', 'requests'];
    // serial
    // inventory.txt
    protected const PATH_CA_PRIVATE_KEY = 'ca_key.pem';
    protected const PATH_CA_CERTIFICATE = 'ca_cert.pem';

    protected ?Certificate $cert = null;
    protected ?PrivateKey $key = null;

    public function __construct(string $basedir)
    {
        $this->initializeDirectoryStructure($basedir, self::DIRECTORIES);
    }

    public function readCertificationRequest(string $certName, string $fingerprint): ?CertificationRequest
    {
        if ($pem = $this->readPEM($this->getCertificationRequestPathByName($certName, $fingerprint))) {
            return CertificationRequest::fromPEM($pem);
        }

        return null;
    }

    public function removeCertificationRequest(string $certName, string $fingerprint): void
    {
        $this->removeFile($this->getCertificationRequestPathByName($certName, $fingerprint));
    }

    public function writeCertificationRequest(CertificationRequest $csr): void
    {
        $this->writeFile($this->getCertificationRequestPath($csr), $csr->toPEM()->string());
    }

    public function readSignedCertificate(string $certName): ?Certificate
    {
        if ($pem = $this->readPEM($this->getCertificatePath($certName))) {
            return Certificate::fromPEM($pem);
        }

        return null;
    }

    public function writeCertificate(Certificate $certificate): void
    {
        $subject = $certificate->tbsCertificate()->subject()->firstValueOf('cn')->stringValue();
        $this->writeFile($this->getCertificatePath($subject), $certificate->toPEM()->string());
    }

    public function readCaCertificate(): ?Certificate
    {
        if ($pem = $this->readPEM(self::PATH_CA_CERTIFICATE)) {
            return Certificate::fromPEM($pem);
        }

        return null;
    }

    public function writeCaCertificate(Certificate $certificate): void
    {
        $this->writeFile(self::PATH_CA_CERTIFICATE, $certificate->toPEM()->string());
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

    protected function getCertificatePath(string $certName): string
    {
        $certName = self::replaceUnsafeCharacters($certName);
        return "signed/$certName.pem";
    }

    protected function getCertificationRequestPath(CertificationRequest $csr): string
    {
        return $this->getCertificationRequestPathByName(
            $csr->certificationRequestInfo()->subject()->firstValueOf('cn')->stringValue(),
            CertificateHelper::fingerprint($csr)
        );
    }

    protected function getCertificationRequestPathByName(string $certName, string $fingerprint): string
    {
        $certName = self::replaceUnsafeCharacters($certName);
        return "requests/$fingerprint-$certName.csr";
    }

    public function listCertificationRequests(): array
    {
        $pending = [];
        $path = $this->realpath('requests') . '/';
        $length = strlen($path);
        $files = glob("$path*.csr");
        if ($files === false) {
            throw new RuntimeException("Failed to glob $path*.csr");
        }
        foreach ($files as $file) {
            $file = substr($file, $length, -4);
            [$fingerprint, $subject] = explode('-', $file, 2);
            $pending[$fingerprint] = $subject;
        }

        return $pending;
    }

    public function storeSignedCertificate(Certificate $certificate, CertificationRequest $csr): void
    {
        $this->writeCertificate($certificate);
        $this->removeFile($this->getCertificationRequestPath($csr));
    }
}
