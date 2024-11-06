<?php

namespace IMEdge\CertificateStore\TrustStore;

use IMEdge\CertificateStore\CertificateHelper;
use IMEdge\CertificateStore\DiskBased\DirectoryBasedComponent;
use IMEdge\CertificateStore\DiskBased\DirectoryHelper;
use Sop\X509\Certificate\Certificate;
use Sop\X509\CertificationPath\CertificationPath;
use Sop\X509\CertificationPath\Exception\PathValidationException;
use Sop\X509\CertificationPath\PathValidation\PathValidationConfig;

class TrustStoreDirectory implements DirectoryBasedComponent, TrustStoreInterface
{
    use DirectoryHelper;

    public function __construct(string $basedir)
    {
        $this->initializeDirectoryStructure($basedir);
    }

    public function addCaCertificate(Certificate $certificate): void
    {
        $this->writeFile($this->getCaCertificatePath($certificate), $certificate->toPEM());
        $this->rehash();
    }

    public function assertValid(Certificate $certificate): void
    {
        if ($issuer = $this->getIssuerForCertificate($certificate)) {
            self::validate($certificate, $issuer);
        } else {
            throw new PathValidationException('Could not find a trusted CA for the given certificate');
        }
    }

    /**
     * @throws PathValidationException
     */
    protected static function validate(Certificate $cert, Certificate $caCert): void
    {
        if ($cert->isSelfIssued()) {
            throw new PathValidationException('Cannot validate self-signed certificate');
        }

        $path = CertificationPath::fromTrustAnchorToTarget($caCert, $cert);
        // foreach ($path->certificates() as $idx => $cert) {
        //     printf("#%d: %s\n", $idx, $cert->tbsCertificate()->subject()->toString());
        // }
        $config = PathValidationConfig::defaultConfig();
        $result = $path->validate($config);
        // printf("Certificate '%s' is valid.\n", $result->certificate()->tbsCertificate()->subject()->toString());
    }

    public function isValid(Certificate $certificate): bool
    {
        try {
            $this->assertValid($certificate);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getIssuerForCertificate(Certificate $certificate): ?Certificate
    {
        // TODO: find by hash
        if ($pem = $this->readPEM('ca_cert.pem')) {
            return Certificate::fromPEM($pem);
        }

        return null;
    }

    protected function rehash(): void
    {
        // TOOD: own implementation. Need to figure out, how to hash subject/issuer with sha1/md5
        // Tested w/o success:
        // hash('sha1', $ca->getCertificate()->tbsCertificate()->subject()->toASN1()->toDER()) . "\n";
        // hash('sha1', $ca->getCertificate()->tbsCertificate()->subject()->toASN1()->toDER()) . "\n";
        // hash('sha1', $myCert->tbsCertificate()->issuer()->toASN1()->toDER()) . "\n";
        // hash('sha1', $myCert->tbsCertificate()->issuer()->toString()) . "\n";
        // hash('sha1', $myCert->tbsCertificate()->issuer()->firstValueOf('cn')->toASN1()->toDER()) . "\n";

        $basedir = $this->getBaseDir();
        `/usr/bin/c_rehash '$basedir'`;
    }

    protected function getCaCertificatePath(Certificate $certificate): string
    {
        return $this->getRelativeCaFileName(
            CertificateHelper::getSubjectName($certificate),
            CertificateHelper::fingerprint($certificate)
        );
    }

    // TODO:
    // public function getCaCertificate(string $caName, string $fingerprint): Certificate;
    // public function hasCaCertificate(string $caName): bool;
    // public function listCaCertificates(): array;
    // public function removeOutdatedCertificates(): void;
    public function getCaCertificate(string $subject, string $fingerprint): ?Certificate
    {
        throw new \RuntimeException('Bad idea.... this method has to die');
        /*
        if ($pem = $this->readPEM($this->getRelativeCaFileName($subject, $fingerprint))) {
            return Certificate::fromPEM($pem);
        }

        return null;
        */
    }

    protected function getRelativeCaFileName(string $subject, string $fingerprint): string
    {
        return sprintf(
            '%s-%s.pem',
            self::replaceUnsafeCharacters($subject),
            // Attack prevention, there are no special characters in fingerprints:
            self::replaceUnsafeCharacters($fingerprint)
        );
    }

    public function hasCaCertificate(string $subject, string $fingerprint): bool
    {
        return $this->hasFile($this->getRelativeCaFileName($subject, $fingerprint));
    }

    public function listCaCertificates(): array
    {
        if ($list = glob($this->basedir . '/*.pem')) {#
            foreach ($list as $filename) {
                // TODO: Not yet, has to be implemented
                echo $filename;
            }
        }
        return [];
    }

    public function getCaPath(): string
    {
        return $this->getBaseDir();
    }
}
