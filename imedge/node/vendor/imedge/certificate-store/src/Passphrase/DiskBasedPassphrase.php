<?php

namespace IMEdge\CertificateStore\Passphrase;

use IMEdge\CertificateStore\DiskBased\FileStore;
use RuntimeException;

use function bin2hex;
use function hrtime;
use function openssl_random_pseudo_bytes;

class DiskBasedPassphrase implements PassphraseProvider
{
    protected const REFRESH_INTERVAL = 60;
    protected const TARGET_FILENAME = 'ca_pass.secret';

    protected FileStore $store;
    protected ?int $loadTime = null;
    protected ?string $phrase = null;

    public function __construct(FileStore $store)
    {
        $this->store = $store;
    }

    public function getPhrase(): string
    {
        if ($this->phrase === null || $this->hasOutdatedPhrase()) {
            $this->phrase = $this->refreshPhrase();
        }

        return $this->phrase;
    }


    protected function hasOutdatedPhrase(): bool
    {
        return ($this->loadTime + self::REFRESH_INTERVAL) < hrtime()[0];
    }

    protected function refreshPhrase(): string
    {
        if ($this->store->hasFile(self::TARGET_FILENAME)) {
            $phrase = $this->store->readFile(self::TARGET_FILENAME);
            if ($phrase === null) {
                throw new RuntimeException('Unable to read phrase from ' . self::TARGET_FILENAME);
            }

            return $phrase;
        } else {
            $phrase = self::generateRandomString();
            $this->store->writeFile(self::TARGET_FILENAME, $phrase, 0600);

            return $phrase;
        }
    }

    protected static function generateRandomString(): string
    {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }
}
