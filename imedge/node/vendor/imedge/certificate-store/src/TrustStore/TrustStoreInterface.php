<?php

namespace IMEdge\CertificateStore\TrustStore;

use Sop\X509\Certificate\Certificate;

interface TrustStoreInterface
{
    public function assertValid(Certificate $certificate): void;
    public function isValid(Certificate $certificate): bool;
    public function addCaCertificate(Certificate $certificate): void;
    public function getCaCertificate(string $subject, string $fingerprint): ?Certificate;
    public function hasCaCertificate(string $subject, string $fingerprint): bool;

    /**
     * @return array<string, string>
     */
    public function listCaCertificates(): array;

    public function getCaPath(): string;
}
