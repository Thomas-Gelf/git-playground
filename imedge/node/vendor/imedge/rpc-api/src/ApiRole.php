<?php

namespace IMEdge\RpcApi;

use Attribute;

#[Attribute]
class ApiRole
{
    public function __construct(public readonly string $name)
    {
    }
}
