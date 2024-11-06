<?php

namespace IMEdge\Protocol\NTP;

use DateTime;
use JsonSerializable;
use stdClass;

class SNTPResponse implements JsonSerializable
{
    public readonly ?float $delay;
    public readonly ?float $offset;

    public function __construct(
        public readonly NtpHeader $request,
        public readonly NtpHeader $response,
        public readonly DateTime $receiveTime,
        public readonly string $ipAddress,
        public readonly int $ntpPort,
    ) {
        $this->response->isIpv6 = Util::ipIsv6($this->ipAddress);
        $stratum = $this->response->stratum?->value;
        if ((0 < $stratum) && ($stratum < 16)) {
            $t1 = $this->request->transmitTimestamp?->toDateTime();
            $t2 = $this->response->receiveTimestamp?->toDateTime();
            $t3 = $this->response->transmitTimestamp?->toDateTime();
            $t4 = $this->receiveTime;
            if ($t3 && $t2 && $t1) {
                $this->delay = Util::calculateDelay($t1, $t2, $t3, $t4);
                $this->offset = Util::calculateOffset($t1, $t2, $t3, $t4);
                return;
            }
        }

        $this->delay = null;
        $this->offset = null;
    }

    public function jsonSerialize(): stdClass
    {
        return (object) [
            'response' => $this->response,
            'serverSocket' => [
                'address' => $this->ipAddress,
                'port'    => $this->ntpPort,
            ],
            'sendTime'    => $this->request->transmitTimestamp?->toDateTime(),
            'receiveTime' => $this->receiveTime,
            'delay'       => $this->delay,
            'offset'      => $this->offset,
        ];
    }
}
