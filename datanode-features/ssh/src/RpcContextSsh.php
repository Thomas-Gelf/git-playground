<?php

namespace IcingaFeature\Ssh;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use React\Promise\PromiseInterface;

class RpcContextSsh implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param array $ips   Ip Address
     * @param array $types Types: dsa, ecdsa, ed25519, rsa, rsa1
     */
    public function keyScanRequest(array $ips, array $types): PromiseInterface
    {
        if (count($types) === 1 && str_contains($types[0], ',')) {
            $types = explode(',', $types[0]);
        }
        $scan = new SshKeyScan($ips, $types, $this->logger);

        return $scan->start();
    }
}
