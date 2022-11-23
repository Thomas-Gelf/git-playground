<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaDataNode\Monitoring\MetricDatatype;
use IcingaFeature\Snmp\DataStructure\DbColumn;
use IcingaFeature\Snmp\DataStructure\DbTable;
use IcingaFeature\Snmp\DataStructure\DeviceIdentifier;
use IcingaFeature\Snmp\DataStructure\Measurement;
use IcingaFeature\Snmp\DataStructure\Metric;
use IcingaFeature\Snmp\DataStructure\Oid;
use IcingaFeature\Snmp\DataStructure\SnmpTable;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

#[SnmpTable([new SnmpTableIndex('ifIndex', new Oid('1.3.6.1.2.1.2.2.1.1'))])]
#[PollingTask(name: 'interfaceTraffic', defaultInterval: 15)]
#[DbTable('network_interface_status', [
    'device_uuid' => 'deviceUuid',
    'if_index'    => 'ifIndex',
])]
#[Measurement('if_traffic', 'ifIndex')]
class PollInterfaceTraffic
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('ifIndex')]
        #[MapLookup('ifNames', 'ifIndex')]
        public readonly int $ifIndex,

        #[Oid('1.3.6.1.2.1.31.1.1.1.6')]
        #[Metric('ifOctetsIn',  MetricDatatype::COUNTER)]
        public readonly int $ifInOctets,

        #[Oid('1.3.6.1.2.1.31.1.1.1.10')]
        #[Metric('ifOctetsOut',  MetricDatatype::COUNTER)]
        public readonly int $ifOutOctets,

        #[Oid('1.3.6.1.2.1.2.2.1.21')]
        #[Metric('ifOutQLen', MetricDatatype::GAUGE)]
        public readonly ?int $ifOutQLen = null,
    ) {}
}
