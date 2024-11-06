<?php

namespace Icinga\Module\Idbdash\CliCommands;

use DateTimeImmutable;
use Icinga\Application\Benchmark;
use Icinga\Cli\Command;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Idbdash\IcingaDbLookup;
use Icinga\Module\Idbdash\TimePeriodHelper;
use Icinga\Module\Idbdash\TimePeriodRange;
use Icinga\Module\Idbdash\TimePeriodSlot;

class LabCommand extends Command
{
    use Database;

    public function testAction(): void
    {
        /*
        january 1: 00:00-8:00, 12:00-23:00
        july 4: 00:00-24:00
        december 25: 00:00-24:00
        december 31: 18:00-24:00
        2017-04-16: 00:00-24:00
        monday -1 may: 00:00-24:00
        monday 1 september: 00:00-24:00
        thursday 4 november: 00:00-24:00
        saturday: 00:00-09:00,18:00-24:00
        sunday: 00:00-09:00,18:00-24:00
         */
        $range = new TimePeriodRange('2025-08-02', '00:00-8:00, 12:00-23:00');
        print_r(
            TimePeriodSlot::createSlotsForRange($range, new DateTimeImmutable(), new DateTimeImmutable('2026-01-01'))
        );
    }



    /**
     * @throws ConfigurationError
     * @api
     */
    public function labAction(): void
    {
        Benchmark::reset();
        Benchmark::measure('ready');
        $db = new IcingaDbLookup();
        $periods = $db->loadTimePeriods();
        Benchmark::measure('loaded');
        $active = TimePeriodHelper::filterActiveTimePeriods($periods);
        Benchmark::measure('filtered');
        foreach ($active as $period) {
            echo $period->name . "\n";
        }
        Benchmark::measure('done');
        Benchmark::dump();
        // var_dump($periods[hex2bin('8ce17a34dde53fc6d676556647e55707902d791c')]); // sla115
        // $period = $periods[hex2bin('8ac56376594a7c8de57cf58ee6fd39892bbe750c')];
        // var_dump($period);
        // print_r($period->getResolvedTimeSlots(new DateTimeImmutable(), new DateTimeImmutable('2025-09-01')));
return;
        foreach ($periods as $period) {
            print_r($period);
            print_r($period->getResolvedTimeSlots(new DateTimeImmutable(), new DateTimeImmutable('2025-09-01')));
        }
    }

    protected function dumpQuery($query)
    {
        list($sql, $values) = $query->getDb()->getQueryBuilder()->assembleSelect($query->assembleSelect());
        echo $sql;
    }
}
