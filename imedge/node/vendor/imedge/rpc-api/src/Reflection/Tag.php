<?php

namespace IMEdge\RpcApi\Reflection;

class Tag
{
    public function __construct(public string $tagType, public string $tagValue)
    {
        $this->parseTagValue(trim($tagValue));
    }

    /**
     * Parse Tag value into Tag-specific properties
     *
     * Override this method for specific tag types
     */
    protected function parseTagValue(string $value): void
    {
    }
}
