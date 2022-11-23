<?php

namespace IcingaFeature\Snmp\SnmpScenario\Event;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;

class EventDispatcher implements EventEmitterInterface
{
    use EventEmitterTrait;
}
