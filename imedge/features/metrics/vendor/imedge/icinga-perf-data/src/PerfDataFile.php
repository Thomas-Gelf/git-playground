<?php

namespace IMEdge\IcingaPerfData;

use IMEdge\Metrics\Ci;
use IMEdge\Metrics\Measurement;
use InvalidArgumentException;

class PerfDataFile
{
    /**
     * @param string $line
     * @return Measurement[]
     */
    public static function parseLine(string $line): array
    {
        /*
        [DATATYPE] => HOSTPERFDATA
        [TIMET] => 1637159980
        [HOSTNAME] => some-host.example.com
        [HOSTPERFDATA] => rta=27.075ms;3000.000;5000.000;0; pl=0%;30;40;; rtmax=27.459ms;;;; rtmin=26.850ms;;;;
        [HOSTCHECKCOMMAND] => check-host-with-backup
        [HOSTSTATE] => UP
        [HOSTSTATETYPE] => HARD
        */

        /*
        DATATYPE::SERVICEPERFDATA
        TIMET::1637160147
        HOSTNAME::some-host.example.com
        SERVICEDESC::Memory Usage
        SERVICEPERFDATA::'Memory in Byte'=32477061;46930330;53634662
        SERVICECHECKCOMMAND::check_snmp
        HOSTSTATE::UP
        HOSTSTATETYPE::HARD
        SERVICESTATE::OK
        SERVICESTATETYPE::HARD
         */

        $result = [
            'DATATYPE' => null,
        ];
        $splitted = preg_split('/\t/', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($splitted === false) {
            throw new \RuntimeException('Unable to split line: '. var_export($line, true));
        }
        foreach ($splitted as $keyValue) {
            $pos = strpos($keyValue, '::');
            if ($pos === false) {
                // TODO: Log? Fail?
                continue;
            }
            $result[substr($keyValue, 0, $pos)] = substr($keyValue, $pos + 2);
        }
        switch ($result['DATATYPE']) {
            case 'HOSTPERFDATA':
                foreach (['HOSTNAME', 'HOSTCHECKCOMMAND', 'HOSTPERFDATA'] as $property) {
                    if (! isset($result[$property])) {
                        throw new InvalidArgumentException(sprintf(
                            '%s is missing, invalid PerfData line: %s',
                            $property,
                            static::shorten($line, 120)
                        ));
                    }
                }
                if (isset($result['HOSTCHECKCOMMAND'])) {
                    $tags = ['check_command' => $result['HOSTCHECKCOMMAND']];
                } else {
                    $tags = [];
                }
                $ci = new Ci($result['HOSTNAME'], null, null, $tags);
                // TODO: Pass state and state type?
                return PerfDataParser::parse($result['HOSTPERFDATA'] ?? '', $ci, (int) ($result['TIMET'] ?? time()));

            case 'SERVICEPERFDATA':
                foreach (['HOSTNAME', 'SERVICEDESC', 'SERVICECHECKCOMMAND', 'SERVICEPERFDATA'] as $property) {
                    if (! isset($result[$property])) {
                        throw new InvalidArgumentException(sprintf(
                            '%s is missing, invalid PerfData line: %s',
                            $property,
                            static::shorten($line, 120)
                        ));
                    }
                }
                if (isset($result['SERVICECHECKCOMMAND'])) {
                    $tags = ['check_command' => $result['SERVICECHECKCOMMAND']];
                } else {
                    $tags = [];
                }

                // TODO: Transform check-multi output into instances!!
                $ci = new Ci($result['HOSTNAME'], $result['SERVICEDESC'], null, $tags);
                return PerfDataParser::parse($result['SERVICEPERFDATA'] ?? '', $ci, (int) ($result['TIMET'] ?? time()));
        }

        throw new InvalidArgumentException(sprintf(
            '"%s" is not a valid PerfData DATATYPE: %s',
            $result['DATATYPE'],
            static::shorten($line, 120)
        ));
    }

    protected static function shorten(string $string, int $length): string
    {
        if (strlen($string) < $length) {
            return $string;
        }

        return substr($string, 0, $length) . '...';
    }
}
