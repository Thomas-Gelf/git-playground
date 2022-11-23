<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaFeature\Snmp\DataStructure\DbColumn;
use IcingaFeature\Snmp\DataStructure\DbTable;
use IcingaFeature\Snmp\DataStructure\DeviceIdentifier;
use IcingaFeature\Snmp\DataStructure\Oid;
use IcingaFeature\Snmp\DataStructure\SnmpTable;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

#[PollingTask(name: 'lmFanSensors', defaultInterval: 300)]
#[SnmpTable([new SnmpTableIndex('lmFanSensorsEntry', new Oid('1.3.6.1.4.1.2021.13.16.3.1.1'))])]
#[DbTable(tableName: 'snmp_lm_fan_sensor', keyProperties: [
    'system_uuid'  => 'systemUuid',
    'sensor_index' => 'sensorIndex',
])]
class PollLmFanSensors
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('system_uuid')]
        public readonly UuidInterface $systemUuid,

        #[SnmpTableIndexValue('lmFanSensorsEntry')]
        #[DbColumn('sensor_index')]
        public readonly int $sensorIndex,

        #[Oid('1.3.6.1.4.1.2021.13.16.3.1.2')]
        #[DbColumn('device')]
        public readonly string $device,

        // The rotation speed of the fan in RPM
        #[Oid('1.3.6.1.4.1.2021.13.16.3.1.3')]
        #[DbColumn('value')]
        public readonly string $value,
    ) {}
}
