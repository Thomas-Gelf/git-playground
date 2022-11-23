<?php

namespace gipfl\CertificateStore\Store;

use Sop\X509\Certificate\Certificate;

class SslContext
{
    protected string $name;
    protected SslStore $store;
    protected ?Certificate $certificate = null;

    public function __construct(string $certName, SslStore $store)
    {
        $this->store = $store;
        $this->name = $certName;
    }

    public function certificateIsSelfSigned(): bool
    {
        return $this->getCertificate()->isSelfIssued();
    }

    public function replaceCertificate(Certificate $certificate, Certificate $caCertificate): void
    {
        $this->store
            ->saveCaCertificate($caCertificate)
            ->saveCertificate($this->name, $certificate);
    }

    /**
     * @return array<string, int|bool|string>
     */
    public function getContextOptionsFor(string $peerName): array
    {
        return $this->store->getSslContextOptions($this->getName(), $peerName);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCertificate(): Certificate
    {
        if ($this->certificate === null) {
            $this->certificate = $this->store->getCertificate($this->getName());
        }

        return $this->certificate;
    }
}
