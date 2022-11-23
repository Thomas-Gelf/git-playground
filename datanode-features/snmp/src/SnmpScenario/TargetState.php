<?php

namespace IcingaFeature\Snmp\SnmpScenario;

enum TargetState: string
{
    case PENDING = 'pending';
    case REACHABLE = 'reachable';
    case FAILING = 'failing';
}
