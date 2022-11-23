<?php

namespace IcingaFeature\Snmp\Scenario;

class ScenarioRegistry
{
    const CLASSES = [
        PollCdpConfig::class,
        PollCdpCache::class,
        PollEntity::class,
        PollEntityLogical::class,
        PollEntityIfMap::class,
        PollInterfaceConfig::class,
        PollInterfaceErrors::class,
        PollInterfacePackets::class,
        PollInterfaceStack::class,
        PollInterfaceStatus::class,
        PollInterfaceTraffic::class,
        PollFilesystems::class,
        PollStorage::class,
        PollProcessList::class,
            // PollIpAddrTable::class,
        PollSensors::class,
        PollSoftwareInstalled::class,
        PollSysInfo::class,

        PollLmTempSensors::class,
        PollLmVoltSensors::class,
        PollLmFanSensors::class,
        
        PollIcomBsTsConfig::class,
        PollIcomBsTsStatus::class,
        PollIcomSensors::class,
    ];
}
