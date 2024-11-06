<?php

namespace IMEdge\CertificateStore\DiskBased;

interface FileStore
{
    public function hasFile(string $filename): bool;
    public function readFile(string $filename): ?string;
    public function writeFile(string $filename, string $content, ?int $mode = null): void;
    public function appendToFile(string $filename, string $content): void;
}
