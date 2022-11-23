<?php

namespace gipfl\CertificateStore\Inventory;

use Sop\X509\Certificate\Certificate;

class MemoryBasedInventory implements InventoryInterface
{
    protected int $serial = 0;

    protected array $knownCerts = [];

    public function getLastSerial(): int
    {
        return $this->serial;
    }

    public function addCertificate(Certificate $cert)
    {
        $tbs = $cert->tbsCertificate();
        $this->knownCerts[sprintf('0x%s08', $tbs->serialNumber())] = $tbs->subject();
    }

    public function getNextSerial(): int
    {
        return ++$this->serial;
    }
}
