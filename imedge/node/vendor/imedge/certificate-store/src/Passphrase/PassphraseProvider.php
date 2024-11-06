<?php

namespace IMEdge\CertificateStore\Passphrase;

interface PassphraseProvider
{
    public function getPhrase(): string;
}
