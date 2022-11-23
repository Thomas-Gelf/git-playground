<?php

namespace IcingaFeature\Inventory;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class RpcContextInventory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected readonly InventoryRunner $runner
    ) {
        $this->logger = new NullLogger();
    }

    public function storeCredentialRequest()
    {
    }

    public function testRequest()
    {
        return true;
    }
    // public function scanTargetRequest($target)
}
