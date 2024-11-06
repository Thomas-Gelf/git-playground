<?php

namespace IMEdge\RpcApi;

use Attribute;

#[Attribute]
class ApiNamespace
{
    public function __construct(public readonly string $name)
    {
    }
}
