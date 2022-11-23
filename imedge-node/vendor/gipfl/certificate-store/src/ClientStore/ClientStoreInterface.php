<?php

namespace gipfl\CertificateStore\ClientStore;

use Sop\CryptoEncoding\PEM;
use Sop\X509\Certificate\Certificate;

interface ClientStoreInterface
{
    public function writeCertificate(Certificate $certificate): void;
    public function readPrivateKeyPEM(): ?PEM;
    public function writePrivateKeyPEM(PEM $pem): void;
}
