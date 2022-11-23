<?php

namespace IcingaDataNode\Command;

use GetOpt\ArgumentException\Missing;
use GetOpt\Command;
use GetOpt\GetOpt;
use GetOpt\Option;
use IcingaDataNode\Application;
use IcingaDataNode\DataNode;
use IcingaDataNode\JsonRpc\RemoteClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class ConnectCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct()
    {
        parent::__construct('connect', $this->handle(...));
        $this->setDescription(sprintf(
            'Connect to a remote %s instance',
            Application::PROCESS_NAME
        ));
        $this->addOptions([
            Option::create(null, 'to', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Connect to, e.g. --to 192.0.2.100:5669'),
            Option::create(null, 'persist')
                ->setDescription('Persist connection, will be re-established at every start'),
        ]);
    }


    public function handle(GetOpt $options): void
    {
        $to = $options->getOption('to');
        if ($to === null) {
            throw new Missing("Option 'to' is required");
        }
        $this->logger->debug('Connecting to ' . $to);
        $rpc = new RemoteClient('unix://' . DataNode::SOCKET_FILE);
        $rpc->request('datanode.connect', [$to, (bool) $options->getOption('persist')])->then(function ($result) {
            // TODO: distinct connected, already connected, connection pending...
            $this->logger->notice('Connected');
            exit(0);
        }, function (\Exception $e) {
            $this->logger->error($e->getMessage());
            exit(1);
        });
    }

    protected function establishConnection()
    {
    }
}
