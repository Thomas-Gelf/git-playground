<?php

namespace IcingaFeature\Snmp\Discovery;

use IcingaFeature\Snmp\Request;
use IcingaFeature\Snmp\RequestedOidList;
use IcingaFeature\Snmp\Result;
use IcingaFeature\Snmp\Runner;
use Psr\Log\LoggerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class IpListScanner
{
    const OIDs = [
        '1.3.6.1.2.1.1.1.0' => 'sys_descr',
        '1.3.6.1.2.1.1.2.0' => 'sys_object_id',
        // '1.3.6.1.2.1.1.3.0' => 'sysUpTime',
        '1.3.6.1.2.1.1.4.0' => 'sys_contact',
        '1.3.6.1.2.1.1.5.0' => 'sys_name',
        '1.3.6.1.2.1.1.6.0' => 'sys_location',
        '1.3.6.1.2.1.1.7.0' => 'sys_services',
        '1.3.6.1.6.3.10.2.1.1.0' => 'engine_id',
        '1.3.6.1.6.3.10.2.1.2.0' => 'engine_boot_count',
    ];

    public static function scan(array $ipList, string $community, LoggerInterface $logger): PromiseInterface
    {
        $results = [];
        $deferred = new Deferred();
        $runner = new Runner();
        $runner->on(Runner::ON_RESULT, function (Result $result) use (&$results, $logger) {
            try {
                $ip = $result->ip;
                if ($result->succeeded()) {
                    $json = [];
                    foreach ($result->result as $key => $value) {
                        $json[$key] = $value;
                    }
                    $logger->debug("Got a response from $ip: " . json_encode($json));
                    $results[$ip] = $json;
                } else {
                    // printf("Error from $ip: %s\n", $result->error);
                }
            } catch (\Throwable $e) {
                $logger->error($e->getMessage());
            }
        });

        shuffle($ipList);
        $oidList = new RequestedOidList(self::OIDs);

        foreach ($ipList as $ip) {
            $runner->enqueue(new Request($ip, $community, $oidList));
        }
        $logger->notice(sprintf("Enqueued %d ips\n", count($ipList)));

        $runner->on(Runner::ON_IDLE, function () use (&$results, $deferred, $logger) {
            $logger->notice('IDLE, sending results: ' . json_encode($results));
            $deferred->resolve($results);
        });

        return $deferred->promise();
    }
}
