<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaDataNode\Monitoring\MetricDatatype;
use IcingaFeature\Snmp\DataStructure\Measurement;
use IcingaFeature\Snmp\DataStructure\Metric;
use IcingaFeature\Snmp\DataStructure\Oid;
use IcingaFeature\Snmp\DataStructure\SnmpTable;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndexValue;

#[SnmpTable([new SnmpTableIndex('ifIndex', new Oid('1.3.6.1.2.1.2.2.1.1'))])]
#[PollingTask(name: 'interfaceError', defaultInterval: 60)]
#[Measurement('if_error', 'ifIndex')]
class PollInterfaceErrors
{
    public function __construct(
        #[SnmpTableIndexValue('ifIndex')]
        public readonly int $ifIndex,

        #[Oid('1.3.6.1.2.1.2.2.1.13')]
        #[Metric('ifInDiscards', MetricDatatype::COUNTER)]
        public readonly int $ifInDiscards,

        #[Oid('1.3.6.1.2.1.2.2.1.14')]
        #[Metric('ifInErrors', MetricDatatype::COUNTER)]
        public readonly int $ifInErrors,

        #[Oid('1.3.6.1.2.1.2.2.1.19')]
        #[Metric('ifOutDiscards', MetricDatatype::COUNTER)]
        public readonly int $ifOutDiscards,

        #[Oid('1.3.6.1.2.1.2.2.1.20')]
        #[Metric('ifOutErrors', MetricDatatype::COUNTER)]
        public readonly int $ifOutErrors,

        // TODO: Check, if in use at all?!
        #[Oid('1.3.6.1.2.1.2.2.1.15')]
        #[Metric('ifInUnknownProtos', MetricDatatype::COUNTER)]
        public readonly int $ifInUnknownProtos,
    ) {}
}
