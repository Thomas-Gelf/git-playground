<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaFeature\Snmp\DataMangler\MangleToBinaryIp;
use IcingaFeature\Snmp\DataStructure\DataNodeIdentifier;
use IcingaFeature\Snmp\DataStructure\DbColumn;
use IcingaFeature\Snmp\DataStructure\DbTable;
use IcingaFeature\Snmp\DataStructure\DeviceIdentifier;
use IcingaFeature\Snmp\DataStructure\Icom\IcomWmacBsTsCfgAdminStatus;
use IcingaFeature\Snmp\DataStructure\Oid;
use IcingaFeature\Snmp\DataStructure\SnmpTable;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

/**
 * PollIcomBsTsConfig
 *
 * The icomWmacBsTsCfgTable table provides BS-side TS-specific configuration parameters
 */
#[SnmpTable([
    new SnmpTableIndex('icomWmacBsId', new Oid('1.3.6.1.4.1.1807.112.1.1.1.1')),
    new SnmpTableIndex('icomWmacBsTsId', new Oid('1.3.6.1.4.1.1807.112.1.3.1.1')),
])]
#[PollingTask(name: 'icomBsTsConfig', defaultInterval: 180)]
#[DbTable(tableName: 'icom_bs_ts_config', keyProperties: [
    'system_uuid' => 'systemUuid',
    'bs_id'       => 'bsId',
    'bs_ts_id'    => 'bsTsId',
])]
class PollIcomBsTsConfig
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('system_uuid')]
        public readonly UuidInterface $systemUuid,

        #[DataNodeIdentifier]
        #[DbColumn('datanode_uuid')]
        public readonly UuidInterface $datanodeUuid,

        #[SnmpTableIndexValue('icomWmacBsId')]
        #[DbColumn('bs_id')]
        public readonly int $bsId,

        #[SnmpTableIndexValue('icomWmacBsTsId')]
        #[DbColumn('bs_ts_id')]
        public readonly int $bsTsId,

        #[DbColumn('status_admin')]
        public readonly ?IcomWmacBsTsCfgAdminStatus $statusAdmin = null,

        #[Oid('1.3.6.1.4.1.1807.112.1.3.1.3')]
        #[DbColumn('config_mac_address')]
        public readonly ?string $cfgMacAddress = null,

        #[Oid('1.3.6.1.4.1.1807.112.1.4.1.17')]
        #[MangleToBinaryIp]
        #[DbColumn('status_ip_address')]
        public readonly ?string $statusIpAddress = null,
    ) {}
}
