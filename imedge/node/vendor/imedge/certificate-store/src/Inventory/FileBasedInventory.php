<?php

namespace IMEdge\CertificateStore\Inventory;

use IMEdge\CertificateStore\DiskBased\FileStore;
use Sop\X509\Certificate\Certificate;

class FileBasedInventory implements InventoryInterface
{
    protected const HEADER = "# Certification Authority - signed certificates\n"
        . "# SERIAL                            NOT_BEFORE                 NOT_AFTER                  SUBJECT\n";
    protected const FILE_INVENTORY = 'inventory.txt';

    protected FileStore $store;

    public function __construct(FileStore $store)
    {
        $this->store = $store;
        $this->initialize();
    }

    public function initialize(): void
    {
        if (! $this->store->hasFile(self::FILE_INVENTORY)) {
            $this->store->writeFile(self::FILE_INVENTORY, self::HEADER);
        }
    }

    public function getNextSerial(): string
    {
        // From TBSCertificate::withRandomSerialNumber()
        $size = 16;
        $num = gmp_intval(gmp_init(mt_rand(1, 0x7f), 10));
        for ($i = 1; $i < $size; ++$i) {
            $num <<= 8;
            $num += mt_rand(0, 0xff);
        }

        return gmp_strval($num, 10);
    }

    public function addCertificate(Certificate $cert): void
    {
        $tbs = $cert->tbsCertificate();
        $line = sprintf(
            "0x%032s  %s  %s  %s\n",
            gmp_strval(gmp_init($tbs->serialNumber(), 10), 16),
            $tbs->validity()->notBefore()->dateTime()->format('c'),
            $tbs->validity()->notAfter()->dateTime()->format('c'),
            '/' . $tbs->subject()
        );

        $this->store->appendToFile(self::FILE_INVENTORY, $line);
    }
}
