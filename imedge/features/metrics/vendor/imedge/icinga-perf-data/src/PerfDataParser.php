<?php

namespace IMEdge\IcingaPerfData;

use IMEdge\Metrics\Ci;
use IMEdge\Metrics\Measurement;

class PerfDataParser
{
    /**
     * @param string $string
     * @param Ci $ci
     * @param int $timestamp
     * @return Measurement[]
     */
    public static function parse(string $string, Ci $ci, int $timestamp): array
    {
        $dataPoints = [];
        foreach (PerfDataStringList::split($string) as $label => $valueString) {
            $ci = clone($ci);
            if ($valueString === '') {
                continue;
            }
            static::extractCheckMultiProperties($label, $ci);
            $key = json_encode($ci);
            $points = $dataPoints[$key] ?? $dataPoints[$key] = new Measurement($ci, $timestamp);
            $points->addMetric(PerfDataStringParser::parseValueString($valueString, $label));
        }

        return $dataPoints;
    }

    protected static function extractCheckMultiProperties(string &$label, Ci &$ci): void
    {
        // interfaces::check_multi::plugins=38 interfaces::check_multi::time=0.95
        // device::check_snmp::uptime=22088146s GigabitEthernet1-0-1::check_snmp::inOctets=4636934887582c
        // GigabitEthernet1-0-1::check_snmp::outOctets=5313497289352c ...

        if (preg_match('/^(.+?)::(.+?)::(.+?)$/', $label, $match)) {
            $label = $match[3];
            $ci = new Ci($ci->hostname, $ci->subject, $match[1], ['check_command' => $match[2]]);
        }
    }
}
