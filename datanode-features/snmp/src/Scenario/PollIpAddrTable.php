<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaFeature\Snmp\DataStructure\DbColumn;
use IcingaFeature\Snmp\DataStructure\DbTable;
use IcingaFeature\Snmp\DataStructure\DeviceIdentifier;
use IcingaFeature\Snmp\DataStructure\InterfaceStatusDuplex;
use IcingaFeature\Snmp\DataStructure\InterfaceStatusOperational;
use IcingaFeature\Snmp\DataStructure\InterfaceStatusStp;
use IcingaFeature\Snmp\DataStructure\Oid;
use IcingaFeature\Snmp\DataStructure\SnmpTable;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndexValue;
use IcingaFeature\Snmp\DataStructure\TruthValue;
use Ramsey\Uuid\UuidInterface;

#[SnmpTable([new SnmpTableIndex('ipAdEntAddr', new Oid('1.3.6.1.2.1.4.20.1.1'))])]
#[PollingTask(name: 'ipAddressTable', defaultInterval: 900)]
#[DbTable('network_interface_status', ['device_uuid', 'if_index'])]
class PollIpAddrTable
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('ifIndex')]
        #[DbColumn('if_index')]
        public readonly int $entityIndex,

        #[DbColumn('status_operational')]
        public readonly InterfaceStatusOperational $statusOperational,

        // #[Oid('1.3.6.1.2.1.2.2.1.9')]
        // public readonly ?DateTime $ifLastChange; // TODO: reliable time-tick diff

        public readonly ?InterfaceStatusDuplex $statusDuplex,

        #[DbColumn('status_stp')]
        public readonly ?InterfaceStatusStp $statusStp,

        #[Oid('1.3.6.1.2.1.17.2.15.1.5')] // stp_port_path_cost
        // TODO: Implement a fallback mechanism, don't read one in case the other one is here
        // #[Oid('1.3.6.1.2.1.17.2.15.1.11')] // stp_port_path_cost32
        public readonly ?int $stpPortPathCost,

        #[Oid('1.3.6.1.2.1.17.2.15.1.8')]
        #[DbColumn('stp_designated_bridge')]
        public readonly ?string $stpDesignatedBridge,

        #[Oid('1.3.6.1.2.1.17.2.15.1.9')]
        #[DbColumn('stp_designated_port')]
        public readonly ?string $stpDesignatedPort,

        #[Oid('1.3.6.1.2.1.17.2.15.1.10')]
        #[DbColumn('stp_forward_transitions')]
        public readonly ?string $stpForwardTransitions,

        /**
         * Promiscuous mode.
         *
         * - false(2) if this interface only accepts packets/frames that are addressed to this station
         */
        #[Oid('1.3.6.1.2.1.31.1.1.1.16')]
        #[DbColumn('promiscuous_mode')]
        public readonly ?TruthValue $promiscuousMode,

        /**
         * Connector present
         *
         * - true(1) if the interface sublayer has a physical connector
         * - false(2) otherwise
         */
        #[Oid('1.3.6.1.2.1.31.1.1.1.17')]
        #[DbColumn('connector_present')]
        public readonly ?TruthValue $connectorPresent,

        // TODO: ifCounterDiscontinuityTime is interesting
    ) {}
}
