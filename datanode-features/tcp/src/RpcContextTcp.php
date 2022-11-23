<?php

namespace IcingaFeature\Tcp;

use Exception;
use React\Dns\Model\Message;
use React\Dns\Query\Query;
use React\Dns\Query\TimeoutExecutor;
use React\Dns\Query\UdpTransportExecutor;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

use function implode;
use function ip2long;
use function ksort;
use function microtime;

class RpcContextTcp
{
    protected TcpConnector|TimeoutConnector $connector;

    public function __construct()
    {
        $connector = new TcpConnector();
        $this->connector = new TimeoutConnector($connector, 5);
    }

    /**
     * @param string $ip    Ip Address
     * @param int    $port  Port (0-65535)
     */
    public function checkRequest(string $ip, int $port): PromiseInterface
    {
        if (self::isIp($ip)) {
            return $this->checkTcp($ip, $port);
        } else {
            $deferred = new Deferred();

            $this->resolveDomain($ip)->then(function ($ips) use ($port, $deferred) {
                $pending = [];
                $result = [
                    'reachable' => false,
                    'message'   => '',
                ];
                $messages = [];
                // $ips[] = '8.8.8.6'; // -> Timeout test
                // TODO: Align logic, single IP check differs from this one.
                //       Should point to the same method, plus collection
                foreach ($ips as $ip) {
                    $handler = function ($res) use (&$result, &$messages, $ip, &$pending, $deferred) {
                        // $result[$ip] = $res;
                        if ($res instanceof Exception) {
                            $messages[$ip] = $res->getMessage();
                            // unset($pending[$ip]);
                        } elseif (is_array($res)) {
                            if ($res['reachable']) {
                                $result['reachable'] = true;
                            }
                            $messages[$ip] = $res['message'];
                            unset($pending[$ip]);
                        } else {
                            $deferred->reject('WHAT?');
                        }
                        if (empty($pending)) {
                            ksort($messages);
                            $result['message'] = implode(', ', $messages);
                            $deferred->resolve($result);
                        }
                    };
                    $pending[$ip] = $this->checkTcp($ip, $port)->then($handler)->otherwise($handler);
                }
            }, function (Exception $e) use ($deferred) {
                $result = [
                    'reachable' => false,
                    'message'   => $e->getMessage(),
                ];
                $deferred->resolve($result);
            });

            return $deferred->promise();
        }
    }

    protected function resolveDomain($domain)
    {
        $connector = new UdpTransportExecutor('9.9.9.9');
        // $connector = new UdpTransportExecutor('172.31.15.1', $this->loop);
        // $connector = new UdpTransportExecutor('192.168.64.9', $this->loop);
        $resolver = new TimeoutExecutor(
            $connector,
            3,
        );

        return $resolver
            ->query(new Query($domain, Message::TYPE_A, Message::CLASS_IN))
            ->then(function (Message $message) {
                $ips = [];
                foreach ($message->answers as $answer) {
                    if ($answer->type === Message::TYPE_A) { // There might be CNAMEs too
                        $ips[] = $answer->data;
                    }
                }

                return $ips;
            });
    }

    protected static function isIp($ip): bool
    {
        return ip2long($ip) !== false;
    }

    protected function checkTcp($ip, $port)
    {
        // Logger::info("IP=$ip");
        $start = microtime(true);

        $success = function (ConnectionInterface $connection) use ($ip, $port, $start) {
            $duration = microtime(true) - $start;
            $connection->close();
            return [
                'reachable' => true,
                'message'   => sprintf(
                    'Successfully connected to %s:%s via TCP in %.2Fms',
                    $ip,
                    $port,
                    $duration * 1000
                )
            ];
        };
        $failure = function (Exception $e) use ($ip, $port, $start) {
            $duration = microtime(true) - $start;
            return [
                'reachable' => false,
                'message' => sprintf(
                    'Failed to connect to %s:%s after %.2Fms', // . $e->getMessage(),
                    $ip,
                    $port,
                    $duration * 1000
                )
            ];
        };

        return $this->connector->connect("tcp://$ip:$port")->then($success, $failure);
    }
}
