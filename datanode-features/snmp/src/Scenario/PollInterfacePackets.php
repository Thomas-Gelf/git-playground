<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaFeature\Snmp\DataStructure\Measurement;
use IcingaFeature\Snmp\DataStructure\Metric;
use IcingaFeature\Snmp\DataStructure\Oid;
use IcingaFeature\Snmp\DataStructure\SnmpTable;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndexValue;

#[SnmpTable([new SnmpTableIndex('ifIndex', new Oid('1.3.6.1.2.1.2.2.1.1'))])]
#[PollingTask(name: 'interfacePacket', defaultInterval: 60)]
#[Measurement('interfacePackets', 'ifIndex')]
class PollInterfacePackets
{
    public function __construct(
        #[SnmpTableIndexValue('ifIndex')]
        public readonly int $ifIndex,

        #[Oid('1.3.6.1.2.1.2.2.1.11')]
        #[Metric('ifInUcastPkts')]
        public readonly int $ifInUcastPkts,

        #[Oid('1.3.6.1.2.1.2.2.1.12')]
        #[Metric('ifInNUcastPkts')]
        public readonly int $ifInNUcastPkts,

        #[Oid('1.3.6.1.2.1.2.2.1.17')]
        #[Metric('ifOutUcastPkts')]
        public readonly int $ifOutUcastPkts,

        #[Oid('1.3.6.1.2.1.2.2.1.18')]
        #[Metric('ifOutNUcastPkts')]
        public readonly int $ifOutNUcastPkts,
    ) {}
}
