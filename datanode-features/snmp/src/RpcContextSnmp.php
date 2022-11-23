<?php

namespace IcingaFeature\Snmp;

use gipfl\Protocol\Snmp\SocketAddress;
use IcingaFeature\Snmp\Discovery\IpListScanner;
use IcingaFeature\Snmp\Scenario\PollEntity;
use IcingaFeature\Snmp\Scenario\PollIcomBsTsConfig;
use IcingaFeature\Snmp\Scenario\PollIcomBsTsStatus;
use IcingaFeature\Snmp\Scenario\PollIcomSensors;
use IcingaFeature\Snmp\Scenario\PollInterfaceConfig;
use IcingaFeature\Snmp\Scenario\PollInterfaceStatus;
use IcingaFeature\Snmp\Scenario\PollSysInfo;
use IcingaFeature\Snmp\Scenario\ScenarioLoader;
use IcingaFeature\Snmp\SnmpScenario\KnownTargetsHealth;
use IcingaFeature\Snmp\SnmpScenario\SnmpTargets;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use React\Promise\PromiseInterface;

use function React\Promise\all;

class RpcContextSnmp implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected ?SnmpSocket $socket;
    protected ScenarioLoader $loader;
    protected bool $shuttingDown = false;

    public function __construct(protected readonly SnmpRunner $runner, LoggerInterface $logger)
    {
        // TODO: v6 socket, socket pool?
        $this->socket = new SnmpSocket();
        $this->setLogger($logger);
        $this->loader = new ScenarioLoader($this->logger);
    }

    public function shutdown(): void
    {
        $this->shuttingDown = true;
    }

    /**
     * @param \IcingaDataNode\RpcDataType\Uuid $credentialUuid
     * @param \gipfl\Protocol\Snmp\SocketAddress $address
     * @param string $name
     * @param \IcingaDataNode\RpcDataType\Uuid $deviceUuid
     * @return PromiseInterface<SnmpResponse>
     * @api
     */
    public function scenarioRequest(
        UuidInterface $credentialUuid, // differs from @param!!
        SocketAddress $address,
        string $name,
        ?UuidInterface $deviceUuid = null,
    ): PromiseInterface {
        $loader = $this->loader;
        $resultHandler = $loader->resultHandler($name);
        $oids = $resultHandler->getScenarioOids();
        if (empty($oids)) {
            throw new InvalidArgumentException("Scenario $name has no OIDs");
        }

        $community = $this->runner->credentials->requireCredential($credentialUuid)->securityName;
        $tables = [];

        if ($resultHandler->needsWalk()) {
            foreach ($oids as $oid => $alias) {
                $tables[$alias] = $this->socket->walk($oid, (string) $address, $community);
            }
            $result = all($tables)->then(function ($result) use ($resultHandler) {
                return $resultHandler->fixResult($result);
            });
        } else {
            $result = $this->socket->get($oids, $address, $community);
        }

        return SnmpRequestHandler::appendResultHandlers($result, $address);
    }

    /**
     * @api
     */
    public function listScenariosRequest(): array
    {
        return $this->loader->listScenarios();
    }

    /**
     * @api
     */
    public function getKnownTargetsHealthRequest(): KnownTargetsHealth
    {
        return $this->runner->health;
    }

    /**
     * @param array $ips
     * @param SnmpCredential $credential
     * @return PromiseInterface
     * @api
     */
    public function scanIpListRequest(array $ips, SnmpCredential $credential): PromiseInterface
    {
        return IpListScanner::scan($ips, $credential->securityName, $this->logger);
    }

    /**
     * @api
     * @param \IcingaFeature\Snmp\SnmpCredentials $credentials
     * @return bool
     */
    public function setCredentialsRequest(SnmpCredentials $credentials): bool
    {
        foreach ($credentials->credentials as $credential) {
            $this->logger->notice(sprintf('Got credential %s(%s)', $credential->name, $credential->uuid->toString()));
        }
        $this->runner->credentials = $credentials;
        return true;
    }

    /**
     * @param \IcingaFeature\Snmp\SnmpScenario\SnmpTargets $targets
     * @return bool
     */
    public function setKnownTargetsRequest(SnmpTargets $targets): bool
    {
        // 1788 targets -> 180kB
        // {"address":{"ip":"194.244.15.28","port":161},"credentialUuid":"92a9178c-6dee-432c-bc67-1d67776454a5"}]},"target":"730345e8-559b-45f3-b89d-184d866964cf","id":4058410},
        // 170 Bytes per target
        $diff = $this->runner->targets->listRemovedTargets($targets);
        $this->runner->targets = $targets;
        foreach ($diff as $name) {
            if (is_string($name)) {
                $this->runner->health->forget($name);
            } else {
                $this->logger->error("Target name is a " . get_debug_type($name));
            }
        }
        // $this->runner->launchPeriodicHealthChecks();
        $this->runner->launchPeriodicScenarios([
            PollSysInfo::class,
            PollEntity::class,
            PollInterfaceConfig::class,
            PollInterfaceStatus::class,
            PollIcomBsTsStatus::class,
            PollIcomBsTsConfig::class,
            PollIcomSensors::class,
        ]);
        return true;
    }

    /**
     * @param \IcingaDataNode\RpcDataType\Uuid $credentialUuid
     * @param \gipfl\Protocol\Snmp\SocketAddress $address
     * @param object $oidList
     * @return \IcingaFeature\Snmp\SnmpResponse
     */
    public function getRequest(
        UuidInterface $credentialUuid, // differs from @param!!
        SocketAddress $address,
        object $oidList,
    ): PromiseInterface {
        $community = $this->runner->credentials->requireCredential($credentialUuid)->securityName;
        return SnmpRequestHandler::appendResultHandlers(
            $this->socket->get((array) $oidList, $address, $community),
            $address
        );
    }

    /**
     * @param \IcingaDataNode\RpcDataType\Uuid $credentialUuid
     * @param \gipfl\Protocol\Snmp\SocketAddress $address
     * @param string $oid
     * @param int $limit Should be ?int, but RPC Metadata still misses related support
     * @param string $nextOid Should be ?string
     * @return PromiseInterface
     */
    public function walkRequest(
        UuidInterface $credentialUuid, // differs from @param!!
        SocketAddress $address,
        string $oid,
        ?int $limit,
        ?string $nextOid
    ): PromiseInterface
    {
        $community = $this->runner->credentials->requireCredential($credentialUuid)->securityName;
        return SnmpRequestHandler::appendResultHandlers(
            $this->socket->walk($oid, (string) $address, $community, $limit, $nextOid),
            $address
        );
    }
}
