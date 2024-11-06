<?php

namespace IMEdge\CertificateStore\DiskBased;

interface DirectoryBasedComponent
{
    public function getBaseDir(): string;
}
