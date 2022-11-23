<?php

namespace gipfl\CertificateStore\TrustStore;

use gipfl\CertificateStore\CertificateHelper;
use gipfl\CertificateStore\DiskBased\DirectoryBasedComponent;
use gipfl\CertificateStore\DiskBased\DirectoryHelper;
use Sop\X509\Certificate\Certificate;

class TrustStoreDirectory implements DirectoryBasedComponent
{
    use DirectoryHelper;

    public function __construct(string $basedir)
    {
        $this->initializeDirectoryStructure($basedir);
    }

    public function addCaCertificate(Certificate $certificate): void
    {
        $this->writeFile($this->getCaCertificatePath($certificate), $certificate->toPEM());
    }

    protected function getCaCertificatePath(Certificate $certificate): string
    {
        return sprintf(
            '%s-%s.pem',
            self::replaceUnsafeCharacters(CertificateHelper::getSubjectName($certificate)),
            CertificateHelper::fingerprint($certificate)
        );
    }

    // TODO:
    // public function getCaCertificate(string $caName, string $fingerprint): Certificate;
    // public function hasCaCertificate(string $caName): bool;
    // public function listCaCertificates(): array;
    // public function removeOutdatedCertificates(): void;
}
