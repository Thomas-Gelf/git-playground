<?php

namespace gipfl\CertificateStore\Store;

use gipfl\CertificateStore\Generator\CsrGenerator;
use gipfl\CertificateStore\OldPrivateKey;
use InvalidArgumentException;
use Sop\CryptoEncoding\PEM;
use Sop\X509\Certificate\Certificate;
use Sop\X509\CertificationRequest\CertificationRequest;

// TODO: Remove, once finally migrated

class SslStore
{
    protected string $path;

    public function __construct(string $path)
    {
        // TODO: signed/ -> certs/
        if (!is_dir($path)) {
            throw new InvalidArgumentException("SslStore path '$path'' is required");
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException("SslStore path '$path' must be writable");
        }

        $this->path = rtrim($path, '/');
    }

    public function getContext(string $name): SslContext
    {
        return new SslContext($name, $this);
    }

    protected function loadCertificate($name): Certificate
    {
        return $this->loadCertificateFromPath($this->getCertificatePath($name));
    }

    public function getSslContextOptions(string $name, string $peerName): array
    {
        if ($this->hasPrivateKey($name)) {
            if (! $this->hasCertificate($name)) {
                $this->createSelfSignedCertificate($name);
            }
        } else {
            $this->createNewKey($name);
        }

        $options = [
            'local_cert' => $this->getCertificatePath($name),
            'local_pk'   => $this->getPrivateKeyPath($name),
            'peer_name' => $peerName,
        ];

        // requires PHP_VERSION_ID >= 50600) {
        // http://php.net/manual/en/migration56.openssl.php#migration56.openssl.metadata
        $options['capture_session_meta'] = true;
        $options['ciphers'] = 'HIGH';

        if ($this->hasCaCertificate()) {
            $options['cafile'] = $this->getCaCertificatePath();
            $options['verify_peer'] = true;
            $options['verify_peer_name'] = true;
        } else {
            $options['verify_peer'] = false;
            $options['verify_peer_name'] = true;
            $options['capture_peer_cert'] = true;
            $options['capture_peer_cert_chain'] = true;
        }

        return $options;
    }

    public function saveCaCertificate(Certificate $certificate): self
    {
        file_put_contents($this->getCaCertificatePath(), $certificate->toPEM()->string());

        return $this;
    }

    public function saveCertificate(string $name, Certificate $certificate): self
    {
        file_put_contents($this->getCertificatePath($name), $certificate->toPEM()->string());

        return $this;
    }

    public function hasCaCertificate(): bool
    {
        return file_exists($this->getCaCertificatePath());
    }

    public function getCaCertificate(): Certificate
    {
        return $this->loadCertificateFromPath($this->getCaCertificatePath());
    }

    protected function loadCertificateFromPath($path): Certificate
    {
        return Certificate::fromPEM(PEM::fromFile($path));
    }

    public function getCaCertificatePath(): string
    {
        return $this->sslFile('certs/ca.crt');
    }

    public function hasCertificate($name): bool
    {
        return file_exists($this->getCertificatePath($name));
    }

    public function getCertificate($name): Certificate
    {
        return $this->loadCertificateFromPath($this->getCertificatePath($name));
    }

    public function requireCertificateRequest($name): CertificationRequest
    {
        $csrFile = $this->getCertificateRequestPath($name);
        if (file_exists($csrFile)) {
            $csr = CsrGenerator::load($csrFile);
        } else {
            $key = $this->getRequiredPrivateKey($name);
            $csr = CsrGenerator::generate($name, $key);
            file_put_contents($csrFile, $csr);
            // $this->logger->notice("Generated a new CSR for $certName");
        }

        return $csr;
    }

    public function getCertificatePath($name): string
    {
        return $this->sslFile("certs/$name.crt");
    }

    public function getCertificateRequestPath($name): string
    {
        return $this->sslFile("requests/$name.crt");
    }

    public function createSelfSignedCertificate(string $name)
    {
        file_put_contents(
            $this->getCertificatePath($name),
            $this->getRequiredPrivateKey($name)
                ->createTemporarySelfSigned($name)->toPEM()->string()
        );
    }

    public function hasPrivateKey($name): bool
    {
        return file_exists($this->getPrivateKeyPath($name));
    }

    public function getPrivateKeyPath($name): string
    {
        return $this->sslFile("keys/$name.key");
    }

    public function getPrivateKey(string $name): OldPrivateKey
    {
        return OldPrivateKey::load($this->getPrivateKeyPath($name));
    }

    public function getRequiredPrivateKey(string $name): OldPrivateKey
    {
        if ($this->hasPrivateKey($name)) {
            return $this->getPrivateKey($name);
        } else {
            return $this->createNewKey($name);
        }
    }

    protected function createNewKey(string $name): OldPrivateKey
    {
        $key = OldPrivateKey::generate();
        $key->store($this->getPrivateKeyPath($name));
        file_put_contents(
            $this->getCertificatePath($name),
            $key->createTemporarySelfSigned($name)->toPEM()->string()
        );

        return $key;
    }

    protected function sslFile(string $file): string
    {
        return $this->path . '/' . $file;
    }
}
