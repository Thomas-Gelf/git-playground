<?php

namespace gipfl\Protocol\Snmp;

enum SnmpPrivProtocol: string
{
    case DES = 'des';
    case AES = 'aes';
}
