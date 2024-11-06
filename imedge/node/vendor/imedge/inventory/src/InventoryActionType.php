<?php

namespace IMEdge\Inventory;

enum InventoryActionType: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
}
