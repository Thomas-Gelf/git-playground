<?php

namespace gipfl\CertificateStore;

use Sop\X509\Certificate\Certificate;
use Sop\X509\CertificationRequest\CertificationRequest;

use function hash;

class CertificateHelper
{
    protected const FINGERPRINT_ALGORITHM = 'sha256';

    public static function fingerprint(Certificate|CertificationRequest $certificate): string
    {
        return hash(self::FINGERPRINT_ALGORITHM, $certificate->toDER());
    }

    public static function getSubjectName(Certificate $certificate): string
    {
        return $certificate->tbsCertificate()->subject()->firstValueOf('cn')->stringValue();
    }
}
