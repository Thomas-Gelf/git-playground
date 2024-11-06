<?php

namespace IMEdge\Tests\RpcApi\Samples;

use IMEdge\RpcApi\ApiMethod;
use IMEdge\RpcApi\ApiMethodType;
use IMEdge\RpcApi\ApiNamespace;
use IMEdge\RpcApi\ApiParameter;
use IMEdge\RpcApi\Reflection\MetaDataParameter;
use SomeNamespace\Deeper\Uuid;
use Whatever\SnmpResponse;

/**
 * This is our sample API
 *
 * It allows to do... some things
 * ...and some more things
 */
#[ApiNamespace('sample')]
class SampleApi
{
    /**
     * This method does something
     *
     * And there is a description, with a lot of details. It covers
     * multiple lines, and there are some paragraphs
     *
     * Lorem ipsum, next paragraph
     *
     * @api
     *
     * @param string $name The name is required
     * @param ?Uuid $uuid Some UUID value
     * @param int|float|null $numberx Intentionally misstyped
     * @return \AnotherNamespace\WithSome\ReturnType[]
     */
    #[ApiMethod('some')]
    public function someMethod(
        /**
         * This is the Name
         */
        string $name,
        #[ApiParameter(new MetaDataParameter('uuid', '\CommonTypes\Uuid', false, true))]
        ?Uuid $uuid = null,
        int|float|null $number = null
    ): array {
        return [];
    }
}
