<?php

namespace IcingaFeature\Snmp\Discovery;

use Exception;
use gipfl\Protocol\Snmp\DataType\OctetString;
use IcingaFeature\Snmp\Agent;

use Throwable;

use function mb_detect_encoding;

class Queue
{
    public StreamingStore $store;

    public function __construct() {
        // interfaces.json, interfacestatus.json, interfaces_t.json, entitymap_t.json
        $this->store = new StreamingStore(__DIR__ . '/entitymap_t.json');
    }

    public function enqueue(Agent $agent, BaseDiscovery $discovery)
    {
        return $discovery->run()->then(function ($result) use (&$pending, $agent) {
            $ip = $agent->ip_address;
            try {
                $final = [];
                foreach ($result as $key => & $res) {
                    $res = self::makeJsonObject($res);
                    $final[$key] = $res;
                }
                $this->store->append($ip, $final);
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
            unset($pending[$ip]);
            printf("Done with %s, %d remaining\n", $ip, count($pending));
        }, function ($e) use ($agent, &$pending) {
            $ip = $agent->ip_address;
            if ($e instanceof Exception) {
                echo $e->getMessage() . "\n";
            } else {
                var_dump($e);
            }
            unset($pending[$ip]);
            printf("%s FAILED, %d remaining\n", $ip, count($pending));
        });
    }

    protected static function makeJsonObject($result): array
    {
        $json = [];
        foreach ($result as $key => $value) {
            if ($value instanceof OctetString) {
                $value = $value->getReadableValue();
                if (mb_detect_encoding($value, 'UTF-8, ISO-8859-1') === 'UTF-8' || str_starts_with($value, '0x')) {
                    $json[$key] = $value;
                } else {
                    $json[$key] = '0x' . bin2hex($value);
                }
            } else {
                $json[$key] = $value->getReadableValue();
            }
        }

        return $json;
    }
}