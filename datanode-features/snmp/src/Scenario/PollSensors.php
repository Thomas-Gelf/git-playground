<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaFeature\Snmp\DataStructure\DbColumn;
use IcingaFeature\Snmp\DataStructure\DbTable;
use IcingaFeature\Snmp\DataStructure\DeviceIdentifier;
use IcingaFeature\Snmp\DataStructure\EntitySensorDataScale;
use IcingaFeature\Snmp\DataStructure\Oid;
use IcingaFeature\Snmp\DataStructure\SnmpTable;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

#[PollingTask('sensors', 300)]
#[DbTable('inventory_physical_entity_sensor', [
    'device_uuid'  => 'deviceUuid',
    'entity_index' => 'entityIndex'
])]
#[SnmpTable([new SnmpTableIndex('entPhysicalIndex', new Oid('1.3.6.1.2.1.47.1.1.1.1.1'))])]
class PollSensors
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('entPhysicalIndex')]
        #[DbColumn('entity_index')]
        public readonly int $entityIndex,

        #[Oid('1.3.6.1.2.1.99.1.1.1.1')] // entPhySensorType
        #[DbColumn('sensor_type')]
        public readonly ?string $sensorType,

        #[DbColumn('sensor_scale')]
        public readonly ?EntitySensorDataScale $sensorScale,

        #[Oid('1.3.6.1.2.1.99.1.1.1.3')] // entPhySensorPrecision
        #[DbColumn('sensor_precision')]
        public readonly ?int $sensorPrecision,

        #[Oid('1.3.6.1.2.1.99.1.1.1.4')] // entPhySensorValue
        #[DbColumn('sensor_value')]
        public readonly ?int $sensorValue,

        #[Oid('1.3.6.1.2.1.99.1.1.1.5')] // entPhySensorOperStatus
        #[DbColumn('sensor_status')]
        public readonly ?int $sensorStatus,

        #[Oid('1.3.6.1.2.1.99.1.1.1.6')] // entPhySensorUnitsDisplay
        #[DbColumn('sensor_units_display')]
        public readonly ?string $sensorUnitsDisplay,

        // '1.3.6.1.2.1.99.1.1.1.7' => 'entPhySensorValueTimeStamp',
        // '1.3.6.1.2.1.99.1.1.1.8' => 'entPhySensorValueUpdateRate',
    ) {}
}
