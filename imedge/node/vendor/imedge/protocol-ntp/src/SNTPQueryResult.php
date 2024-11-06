<?php

namespace IMEdge\Protocol\NTP;

use Exception;
use JsonSerializable;
use stdClass;

class SNTPQueryResult implements JsonSerializable
{
    /** @var SNTPResponse[] */
    public array $responses = [];
    /** @var string[] */
    public array $resolvedIpAddresses = [];
    /** @var Exception[] */
    public array $errors = [];
    public bool $timedOut = false;
    public ?float $dnsQueryTime = null;
    public ?float $ntpQueryTime = null;
    public ?float $averageOffset = null;

    /**
     * @param string[] $requestedServers
     */
    public function __construct(public readonly array $requestedServers)
    {
    }

    public function addResponse(SNTPResponse $response): void
    {
        $this->responses[] = $response;
    }

    public function addError(string $ip, Exception $error): void
    {
        $this->errors[$ip] = $error;
    }

    /**
     * @return object[]
     */
    protected function serializeErrors(): array
    {
        $errors = [];
        foreach ($this->errors as $ip => $error) {
            $errors[] = (object) [
                'serverIp' => $ip,
                'message'  => $error->getMessage(),
            ];
        }

        return $errors;
    }

    public function jsonSerialize(): stdClass
    {
        return (object) [
            'averageOffset'    => $this->averageOffset,
            'requestedServers' => $this->requestedServers,
            'resolvedIpAddresses' => $this->resolvedIpAddresses,
            'responses'     => $this->responses,
            'errors'        => $this->serializeErrors(),
            'timedOut'      => $this->timedOut,
            'timings' => [
                'DNS Lookups' => $this->dnsQueryTime,
                'NTP Queries' => $this->ntpQueryTime,
            ],
        ];
    }
}
