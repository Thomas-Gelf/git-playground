<?php

namespace IcingaDataNode\Inventory;

use IcingaDataNode\NodeIdentifier;
use React\Promise\PromiseInterface;

interface CentralInventory
{
    /**
     * @param InventoryAction[] $actions
     * @return PromiseInterface<InventoryProcessingResult>
     */
    public function shipBulkActions(array $actions): PromiseInterface;

    public function getCredentials(): array;

    public function loadTableSyncPositions(NodeIdentifier $nodeIdentifier): array;
}
