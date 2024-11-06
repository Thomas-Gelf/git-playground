<?php

namespace IMEdge\Protocol\NTP;

use Amp\Cancellation;
use DateTime;
use RuntimeException;

use function Amp\Socket\connect;

class NtpClient
{
    public static function send(
        NtpHeader $request,
        Cancellation $cancellation,
        string $ip,
        int $port = 123
    ): SNTPResponse {
        $socket = connect(Util::makeUri($ip, $port));
        $request->transmitTimestamp = NtpTimestampFormat::now();

        $socket->write($request);
        $binaryResponse = $socket->read($cancellation);
        $receiveTime = new DateTime();
        if ($binaryResponse === null) {
            $socket->close();
            throw new RuntimeException('Failed to read NTP response');
        }
        $socket->close();

        return new SNTPResponse($request, NtpHeader::parse($binaryResponse), $receiveTime, $ip, $port);
    }
}
