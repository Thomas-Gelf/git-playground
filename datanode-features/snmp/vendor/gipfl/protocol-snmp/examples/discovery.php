<?php

use gipfl\Protocol\Snmp\DataType\DataType;
use gipfl\Protocol\Snmp\Socket;
use React\EventLoop\Loop;
use React\Promise\ExtendedPromiseInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$socket = new Socket();
$community = 'public';
$oids = [
    '1.3.6.1.2.1.1.5.0' => 'sysName',
    '1.3.6.1.2.1.1.6.0' => 'sysLocation',
    '1.3.6.1.2.1.25.3.2.1.3.1' => 'hrDeviceDescr',
    '1.3.6.1.2.1.25.3.2.1.5.1' => 'hrDeviceStatus',
    '1.3.6.1.2.1.25.3.5.1.1.1' => 'hrPrinterStatus',
    '1.3.6.1.2.1.2.2.1.6.1' => 'ifPhysAddress',
    '1.3.6.1.2.1.43.16.5.1.2.1.1' => 'prtConsoleDisplayBufferText',
    '1.3.6.1.2.1.43.10.2.1.2.1.1' => 'prtMarkerMarkTech',

    // IEEE1284 Device ID bei HP teilweise unter: 1.3.6.1.4.1.11.2.3.9.1.1.7.0
    // Ricoh: 1.3.6.1.4.1.367.3.2.1.1.1.11
    '1.3.6.1.4.1.2435.2.3.9.1.1.7.0' => 'brieee1284id', // brother.generalDeviceStatus.brieee1284id
    '1.3.6.1.4.1.2435.2.4.3.2435.5.17.1.0' => 'brEWSSupported',
    '1.3.6.1.4.1.2435.2.4.3.2435.5.17.2.0' => 'brEWSEnable',
];

/*
 hrDeviceStatus
                                    [5] => down
                                    [1] => unknown
                                    [4] => testing
                                    [3] => warning
                                    [2] => running
// printerSTatus
                   other(1),
                   unknown(2),
                   idle(3),
                   printing(4),
                   warmup(5)
 */

$ip = '255.255.255.255';
$ip = '192.168.178.88';
Loop::addTimer(5, function () {
    echo "Timeout\n";
    Loop::stop();
});

/**
 * @param string $id
 * @return array<string, ?string>
 */
function parseIeee1284DeviceId(string $id): array
{
    $shortcuts = [
        // Required:
        'MFG' => 'Manufacturer',
        'MDL' => 'Model',

        'CMD' => 'Command Set', // PJL bei Scanner, "MLC,PCL,PML,DW-PCL,DESKJET,DYN" bei Drucker

        // Optional:
        'CLS' => 'Device Class', // PRINTER, MODEM, NET, HDC1, PCMCIA, MEDIA2, FDC3, PORTS, SCANNER, DIGCAM
        'DES' => 'Description', // Noch nicht gesehen, sollte nicht länger als 128 Zeichen sein
        'CID' => 'Compatible ID', // may have any value that exactly matches an ID value listed in an INF file.Plug&Play
        'URF' => 'Apple Raster (URF)',
        'SN'  => 'Serial Number',

        // MFG:RICOH;CMD:PJL,RCS,PCL,PCLXL,POSTSCRIPT;MDL:Aficio MP C3500;STS:10072/10033,0;\
        //      CLS:PRINTER;DES:RICOH Aficio MP C3500;"
        // -> STS??
    ];
    $result = [];
    $parts = preg_split('/\s*;\s*/', trim($id), -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        throw new RuntimeException("Unable to process device id '$id': " . preg_last_error_msg());
    }
    foreach ($parts as $part) {
        if (str_contains($part, ':')) {
            [$key, $value] = explode(':', $part, 2);
        } else {
            $key = $part;
            $value = null;
        }
        if (isset($shortcuts[$key])) {
            $key = $shortcuts[$key];
        }
        $result[$key] = $value;
    }

    return $result;
}

$promise = $socket->get($oids, $ip, $community)->then(function ($result) {
    /** @var DataType $value */
    foreach ($result as $key => $value) {
        if ($key === 'brieee1284id') {
            printf("%s: %s\n", $key, print_r(parseIeee1284DeviceId($value->getReadableValue()), true));
        } else {
            printf("%s: %s\n", $key, $value->getReadableValue());
        }
    }
}, function ($reason) use ($ip) {
    echo "No Response from $ip: $reason\n";
});
assert($promise instanceof ExtendedPromiseInterface);
$promise->always(function () use ($socket, $ip) {
    if (! $socket->hasPendingRequests()) {
        printf("Done with IP %s\n", $ip);
        //Loop::stop();
    }
});

Loop::run();
