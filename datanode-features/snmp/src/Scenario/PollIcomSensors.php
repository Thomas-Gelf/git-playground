<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaFeature\Snmp\DataStructure\DbColumn;
use IcingaFeature\Snmp\DataStructure\DbTable;
use IcingaFeature\Snmp\DataStructure\DeviceIdentifier;
use IcingaFeature\Snmp\DataStructure\IcomEntitySensorDataScale;
use IcingaFeature\Snmp\DataStructure\Oid;
use IcingaFeature\Snmp\DataStructure\SnmpTable;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

#[PollingTask('icomSensors', 300)]
#[DbTable('inventory_physical_entity_sensor', [
    'device_uuid'  => 'deviceUuid',
    'entity_index' => 'entityIndex'
])]
#[SnmpTable([new SnmpTableIndex('entPhysicalIndex', new Oid('1.3.6.1.2.1.47.1.1.1.1.1'))])]
// #[Confine([
//    new HasOidConfinement('1.3.6.1.4.1.1807.30.1.1'),
// ])]
// #[ScenarioPriority(1)]
class PollIcomSensors
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('entPhysicalIndex')]
        #[DbColumn('entity_index')]
        public readonly int $entityIndex,

        #[Oid('1.3.6.1.4.1.1807.30.1.1.1.1')] // entPhySensorType
        #[DbColumn('sensor_type')]
        public readonly ?string $sensorType = null,

        // TODO: Check, whether we can override the OID for given ENUM
        #[DbColumn('sensor_scale')]
        public readonly ?IcomEntitySensorDataScale $sensorScale = null,

        #[Oid('1.3.6.1.4.1.1807.30.1.1.1.3')] // entPhySensorPrecision
        #[DbColumn('sensor_precision')]
        public readonly ?int $sensorPrecision = null,

        #[Oid('1.3.6.1.4.1.1807.30.1.1.1.4')] // entPhySensorValue
        #[DbColumn('sensor_value')]
        public readonly ?int $sensorValue = null,

        #[Oid('1.3.6.1.4.1.1807.30.1.1.1.5')] // entPhySensorOperStatus
        #[DbColumn('sensor_status')]
        public readonly ?int $sensorStatus = null,

        // Does not exist
        public readonly ?string $sensorUnitsDisplay = null,

        // '1.3.6.1.4.1.1807.30.1.1.1.8' => icomEntSensorFailureStatus (Power supply status)
        // '1.3.6.1.4.1.1807.30.1.1.1.6' => icomEntSensorValueTimeStamp
        // '1.3.6.1.4.1.1807.30.1.1.1.7' => icomEntSensorValueUpdateRate
    ) {}
}
