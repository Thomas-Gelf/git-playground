<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SnmpTableHelper
{
    /**
     * @param SnmpTableIndex[] $indexes
     */
    public static function flattenResult(LoggerInterface $logger, array $indexes, array $result, array $keys): array
    {
        $final = [];
        foreach ($result as $table => $results) {
            foreach ($results as $oid => $value) {
                $combinedIndex = '';
                $row = [];
                foreach ($indexes as $index) {
                    if ($index->implicit) {
                        $idxValue = self::stripIndexFromOid($oid, $index->length);
                    } else {
                        throw new RuntimeException('Only implicit indexes for now');
                    }

                    if ($combinedIndex !== '') {
                        $combinedIndex .= '.';
                    }
                    $combinedIndex .= $idxValue;
                    $row[$index->name] = $idxValue;
                }
                foreach ($row as $k => $v) {
                    $final[$combinedIndex][$k] ??= $v; // TODO: Object with type
                }
                $final[$combinedIndex][$keys[$oid]] = $value;
            }
        }

        return $final;
    }

    // TODO: Index type, not implicit length...
    protected static function stripIndexFromOid(string &$oid, int $length): string
    {
        $pos = strrpos($oid, '.', $length);
        $suffix = substr($oid, $pos + 1);
        $oid = substr($oid, 0, $pos);

        return $suffix;
    }
}
