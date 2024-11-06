<?php

namespace IMEdge\CertificateStore\Inventory;

use Sop\X509\Certificate\Certificate;

interface InventoryInterface
{
    public function getNextSerial(): string;

    public function addCertificate(Certificate $cert): void;
}
