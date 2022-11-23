<?php

namespace gipfl\CertificateStore\Passphrase;

interface PassphraseProvider
{
    public function getPhrase(): string;
}
