<?php

namespace gipfl\CertificateStore\Store;

class ClientSslStore extends SslStore
{
    public function getCaCertificatePath(): string
    {
        return $this->sslFile('ca.crt');
    }

    public function getCertificatePath($name): string
    {
        return $this->sslFile("$name.crt");
    }

    public function getPrivateKeyPath($name): string
    {
        return $this->sslFile("$name.key");
    }

    public function getCertificateRequestPath($name): string
    {
        return $this->sslFile("$name.csr");
    }
}
