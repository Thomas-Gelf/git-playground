<?php

use gipfl\Protocol\Snmp\GetRequest;
use gipfl\Protocol\Snmp\SimpleRequestIdGenerator;
use gipfl\Protocol\Snmp\SnmpV2Message;
use gipfl\Protocol\Snmp\VarBind;

require dirname(__DIR__) . '/vendor/autoload.php';

$community = 'public';
$ip = '192.0.2.1';
$oidList = [
    '1.3.6.1.2.1.1.1.0' => 'sysDescr',
    '1.3.6.1.2.1.1.2.0' => 'sysObjectID',
    '1.3.6.1.2.1.1.3.0' => 'sysUpTime',
    '1.3.6.1.2.1.1.4.0' => 'sysContact',
    '1.3.6.1.2.1.1.5.0' => 'sysName',
    '1.3.6.1.2.1.1.6.0' => 'sysLocation',
    '1.3.6.1.2.1.1.7.0' => 'sysServices',
];

$start = microtime(true);
$out = '';
$idGenerator = new SimpleRequestIdGenerator();
for ($i = 0; $i < 100000; $i++) {
    // $id = $idGenerator->getNextId(); -> 0.04ms for 100.000 ids
    $id = 10;
    $varBinds = [];
    foreach ($oidList as $oid => $target) {
        $binds[] = new VarBind($oid);
    }

    $request = new SnmpV2Message($community, new GetRequest($varBinds, $id));
    $out = $request->toBinary();
    // $out = $request->getPdu()->toASN1()->toDER();
    // $out = $request->toBinary();
}

printf("It took %.02fms\n", microtime(true) - $start);
