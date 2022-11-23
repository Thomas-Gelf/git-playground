<?php

namespace IcingaDataNode;

use Sop\ASN1\Element;
use Sop\ASN1\Type\Primitive\ObjectIdentifier;
use Sop\X509\Certificate\Extension\Extension;

class IcingaRpcAdminPermission extends Extension
{
    protected function _valueASN1(): Element
    {
        return new ObjectIdentifier('1.3.6.1.4.1.26840.5670.509.1');
    }
}
