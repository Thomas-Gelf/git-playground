<?php

namespace gipfl\CertificateStore\DiskBased;

interface DirectoryBasedComponent
{
    public function getBaseDir(): string;
}
