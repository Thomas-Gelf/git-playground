<?php

namespace IMEdge\RpcApi;

use Attribute;
use IMEdge\RpcApi\Reflection\MetaDataParameter;

#[Attribute]
class ApiParameter
{
    public function __construct(public readonly MetaDataParameter $parameter)
    {
    }
}
