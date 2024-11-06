<?php

namespace IMEdge\RpcApi;

use Attribute;

#[Attribute]
class ApiMethod
{
    public function __construct(public readonly ?string $name = null)
    {
    }
}
