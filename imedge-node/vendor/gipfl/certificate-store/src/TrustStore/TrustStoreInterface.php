<?php

namespace gipfl\CertificateStore\TrustStore;

use Sop\X509\Certificate\Certificate;

interface TrustStoreInterface
{
    public function addCaCertificate(Certificate $certificate): void;
    public function getCaCertificate(string $caName): Certificate;
    public function hasCaCertificate(string $caName): bool;

    /**
     * @return array<string, string>
     */
    public function listCaCertificates(): array;
    /**
     * @return array<string, int|string|bool>
     */
    public function getSslClientContextOptions(): array;
    public function getCaPath(): string;
}
