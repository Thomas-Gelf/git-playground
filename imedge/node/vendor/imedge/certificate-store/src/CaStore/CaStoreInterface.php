<?php

namespace IMEdge\CertificateStore\CaStore;

use Sop\CryptoEncoding\PEM;
use Sop\X509\Certificate\Certificate;
use Sop\X509\CertificationRequest\CertificationRequest;

interface CaStoreInterface
{
    /**
     * @return array<string, string>
     */
    public function listCertificationRequests(): array;
    public function readCertificationRequest(string $certName, string $fingerprint): ?CertificationRequest;
    public function writeCertificationRequest(CertificationRequest $csr): void;
    public function removeCertificationRequest(string $certName, string $fingerprint): void;
    public function storeSignedCertificate(Certificate $certificate, CertificationRequest $csr): void;
    public function readSignedCertificate(string $certName): ?Certificate;
    public function writeCertificate(Certificate $certificate): void;
    public function readCaCertificate(): ?Certificate;
    public function writeCaCertificate(Certificate $certificate): void;
    public function readPrivateKeyPEM(): ?PEM;
    public function writePrivateKeyPEM(PEM $pem): void;
}
