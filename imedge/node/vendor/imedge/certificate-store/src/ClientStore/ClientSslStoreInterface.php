<?php

namespace IMEdge\CertificateStore\ClientStore;

use Sop\CryptoTypes\Asymmetric\PrivateKey;
use Sop\X509\Certificate\Certificate;

interface ClientSslStoreInterface
{
    public function readCertificate(string $certName): ?Certificate;
    public function writeCertificate(Certificate $certificate): void;
    public function readPrivateKey(string $certName): ?PrivateKey;
    public function writePrivateKey(string $certName, PrivateKey $privateKey): void;
    public function readCaCertificate(): ?Certificate;
    public function writeCaCertificate(Certificate $certificate): void;
}
