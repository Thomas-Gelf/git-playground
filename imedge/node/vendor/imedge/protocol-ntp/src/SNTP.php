<?php

namespace IMEdge\Protocol\NTP;

use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\TimeoutCancellation;
use Amp\TimeoutException;
use Exception;
use RuntimeException;

use function Amp\async;
use function Amp\Future\awaitAll;

class SNTP
{
    /**
     * @param string[] $servers
     */
    public static function query(array $servers): SNTPQueryResult
    {
        $queryResult = new SNTPQueryResult($servers);
        $tsStart = hrtime(true);
        $ips = [];
        foreach ($servers as $server) {
            // TODO: parallel DNS lookups
            $ips = array_merge($ips, Util::getIps($server));
        }
        $queryResult->resolvedIpAddresses = $ips;
        $tsHaveIps = hrtime(true);
        $queryResult->dnsQueryTime = ($tsHaveIps - $tsStart) / Util::NANO_TO_SECONDS;
        if (empty($ips)) {
            throw new RuntimeException(sprintf('Got no suitable server for %s', implode(', ', $servers)));
        }

        $deferredCancellation = new DeferredCancellation();
        $sufficientResultCount = 3;
        $cntSuccessful = 0;
        foreach ($ips as $ip) {
            $promises[$ip] = async(function () use (
                $ip,
                $queryResult,
                &$cntSuccessful,
                $sufficientResultCount,
                $deferredCancellation,
            ) {
                $request = SNTP::prepareRequest();
                try {
                    $response = NtpClient::send($request, $deferredCancellation->getCancellation(), $ip);
                } catch (Exception $e) {
                    $queryResult->addError($ip, $e);
                    return $e;
                }
                // TODO: verify quality, stratum, before keeping the result
                $queryResult->addResponse($response);
                if (
                    $response->response->leapIndicator === NtpLeapIndicator::NO_WARNING
                    && ($response->response->stratum?->value > 0)
                    && ($response->response->stratum->value < 16)
                ) {
                    $cntSuccessful++;
                }
                if ($cntSuccessful >= $sufficientResultCount) {
                    $deferredCancellation->cancel();
                }

                return $response;
            });
        }

        try {
            awaitAll($promises, new TimeoutCancellation(1));
        } catch (CancelledException $e) {
            if ($e->getPrevious() instanceof TimeoutException) {
                $queryResult->timedOut = true;
            } elseif ($previous = $e->getPrevious()) {
                throw $previous;
            } else {
                throw $e;
            }
        }

        $tsAllDone = hrtime(true);
        $queryResult->ntpQueryTime = ($tsAllDone - $tsHaveIps) / Util::NANO_TO_SECONDS;
        $deferredCancellation->cancel();

        $offsets = [];
        foreach ($queryResult->responses as $result) {
            if ($result->offset !== null) {
                $offsets[] = $result->offset;
            }
        }
        if (! empty($offsets)) {
            $queryResult->averageOffset = array_sum($offsets) / count($offsets);
        }

        return $queryResult;
    }

    public static function prepareRequest(): NtpHeader
    {
        $request = new NtpHeader(NtpAssociationMode::CLIENT, NtpVersion::V4);
        $request->rootDelay = new NtpShortFormat(1);
        $request->rootDispersion = new NtpShortFormat(1);
        $request->leapIndicator = NtpLeapIndicator::ALARM_CONDITION;
        $request->pollInterval = 3;
        $request->precision = -6;

        return $request;
    }
}
