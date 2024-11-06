<?php

namespace IMEdge\RpcApi\Reflection;

class MetaDataTagParser
{
    protected const DEFAULT_TAG_TYPE = Tag::class;

    protected const SPECIAL_TAGS = [
        'param'  => ParamTag::class,
        'throws' => ThrowsTag::class,
        'return' => ReturnTag::class,
    ];

    public function __construct(
        public readonly string $tagType,
        public string $string
    ) {
    }

    public function getTag(): Tag
    {
        $type = $this->tagType;
        $tags = static::SPECIAL_TAGS;
        if (isset($tags[$type])) {
            $class = self::SPECIAL_TAGS[$type];
        } else {
            $class = self::DEFAULT_TAG_TYPE;
        }

        return new $class($type, $this->string);
    }

    public function appendValueString(string $string): void
    {
        $this->string .= $string;
    }
}
