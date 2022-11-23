<?php

use gipfl\Protocol\Snmp\DataType\DataType;
use gipfl\Protocol\Snmp\Socket;
use React\EventLoop\Loop;

require dirname(__DIR__) . '/vendor/autoload.php';

$socket = new Socket();
$user = 'moni';
$authPass = null;
$privPass = null;

$ip = '10.90.90.91';
Loop::addTimer(5, function () {
    echo "Timeout\n";
    Loop::stop();
});

$packet = new \gipfl\Protocol\Snmp\SnmpV3Message(
    new \gipfl\Protocol\Snmp\Snmpv3Header(
        messageId: 123,
        securityFlags: \gipfl\Protocol\Snmp\SnmpSecurityLevel::NO_AUTH_NO_PRIV,
        reportableFlag: true,
    ),
    new \gipfl\Protocol\Snmp\Usm\UserBasedSecurityModel(''),
    new \gipfl\Protocol\Snmp\Snmpv3ScopedPdu(
        new \gipfl\Protocol\Snmp\GetRequest([], 123),
        '',
        ''
    )
);

$socket->send($packet, $ip)->then(function ($message) use ($socket, $ip) {
    assert($message instanceof \gipfl\Protocol\Snmp\SnmpV3Message);
    if ($message->securityParameters instanceof \gipfl\Protocol\Snmp\Usm\UserBasedSecurityModel) {
        $engineTime = $message->securityParameters->engineTime;
        $engineId = $message->securityParameters->engineId;
        var_dump($engineId);
        $boots = $message->securityParameters->engineBoots;

        $packet = new \gipfl\Protocol\Snmp\SnmpV3Message(
            new \gipfl\Protocol\Snmp\Snmpv3Header(
                messageId: 125,
                securityFlags: \gipfl\Protocol\Snmp\SnmpSecurityLevel::AUTH_NO_PRIV,
                reportableFlag: true,
            ),
            new \gipfl\Protocol\Snmp\Usm\UserBasedSecurityModel(
                'moni',
                $engineId,
                $boots,
                $engineTime
            ),
            new \gipfl\Protocol\Snmp\Snmpv3ScopedPdu(
                new \gipfl\Protocol\Snmp\GetRequest([
                    new \gipfl\Protocol\Snmp\VarBind('1.3.6.1.2.1.1.5.0')
                ], 125),
                $engineId,
                ''
            )
        );
        $authenticator = new \gipfl\Protocol\Snmp\Usm\MessageAuthenticator();
        $message = $authenticator->authenticateOutgoingMsg(
            $packet,
            'md5',
            \gipfl\Protocol\Snmp\Usm\AuthKey::hash('md5', 'insecure', $engineId)
        );
        $socket->send($message, $ip)->then(function (\gipfl\Protocol\Snmp\SnmpV3Message $message) {
            // var_dump($message);
            if ($message->getPdu()->isError()) {
                var_dump('ERROR');
            } else {
                foreach ($message->getPdu()->varBinds as $varBind) {
                    echo $varBind->oid . ': ' . $varBind->value->getReadableValue() . "\n";
                }
            }
        });
    }
}, function (Exception $e) {
    var_dump($e->getMessage());
});

Loop::run();
