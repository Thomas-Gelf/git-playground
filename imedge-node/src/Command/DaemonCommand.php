<?php

namespace IcingaDataNode\Command;

use GetOpt\Command;
use GetOpt\GetOpt;
use IcingaDataNode\Application;
use IcingaDataNode\Daemon\SimpleDaemon;
use IcingaDataNode\DataNode;

class DaemonCommand extends Command
{
    public function __construct()
    {
        parent::__construct('daemon', [$this, 'handle']);
        // $this->addOperand(Operand::create('directory')->setDescription('Directory, defaults to $HOME'));
        $this->setDescription(sprintf('Run the %s daemon', Application::PROCESS_NAME));
    }

    public function handle(GetOpt $options): void
    {
        // $home = $options->getOperand('directory');
        $home ??= $_ENV['HOME'] ?? $_SERVER['HOME'];
        if ($home === null) {
            echo "Got no --directory and could not detect \$HOME\n";
            exit(1);
        }
        $logger = ProcessLogger::createForOptions($options);
        ProcessLogger::detectAndApplyLogWriter($logger, Application::LOG_NAME, $options);
        Application::checkRequirements($logger);

        $daemon = new SimpleDaemon();
        $daemon->setLogger($logger);
        $daemon->attachTask(new DataNode($home, $logger));
        $daemon->run();
    }
}
