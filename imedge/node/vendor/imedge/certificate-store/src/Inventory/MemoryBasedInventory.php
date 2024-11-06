<?php

namespace IMEdge\CertificateStore\Inventory;

use Sop\X509\Certificate\Certificate;

class MemoryBasedInventory implements InventoryInterface
{
    protected int $serial = 0;

    /** @var array<non-empty-string, string> */
    protected array $knownCerts = [];

    public function getLastSerial(): int
    {
        return $this->serial;
    }

    public function addCertificate(Certificate $cert): void
    {
        $tbs = $cert->tbsCertificate();
        $this->knownCerts[sprintf('0x%s08', $tbs->serialNumber())] = $tbs->subject()->toString();
    }

    public function getNextSerial(): string
    {
        return (string) ++$this->serial;
    }
}
