<?php

namespace gipfl\Protocol\Snmp;

enum SnmpAuthProtocol: string
{
    case MD5  = 'md5';
    case SHA = 'sha';
}
