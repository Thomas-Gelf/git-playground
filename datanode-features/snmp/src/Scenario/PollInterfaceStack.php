<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaFeature\Snmp\DataStructure\DbColumn;
use IcingaFeature\Snmp\DataStructure\DbTable;
use IcingaFeature\Snmp\DataStructure\DeviceIdentifier;
use IcingaFeature\Snmp\DataStructure\Oid;
use IcingaFeature\Snmp\DataStructure\RowStatus;
use IcingaFeature\Snmp\DataStructure\SnmpTable;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

#[SnmpTable([
    new SnmpTableIndex('ifStackHigherLayer', new Oid('1.3.6.1.2.1.31.1.2.1.1')),
    new SnmpTableIndex('ifStackLowerLayer', new Oid('1.3.6.1.2.1.31.1.2.1.2')),
])]
#[PollingTask(name: 'interfaceStack', defaultInterval: 60)]
#[DbTable('network_interface_stack', [
    'device_uuid'     => 'deviceUuid',
    'higher_if_index' => 'higherIfIndex',
    'lower_if_index'  => 'lowerIfIndex'
])]
class PollInterfaceStack
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('ifStackHigherLayer')]
        #[DbColumn('higher_if_index')]
        public readonly int $higherIfIndex,

        #[SnmpTableIndexValue('ifStackLowerLayer')]
        #[DbColumn('lower_if_index')]
        public readonly int $lowerIfIndex,

        #[DbColumn('if_stack_status')]
        #[Oid('1.3.6.1.2.1.31.1.2.1.3')]
        public readonly RowStatus $ifStackStatus,
    ) {}
}
