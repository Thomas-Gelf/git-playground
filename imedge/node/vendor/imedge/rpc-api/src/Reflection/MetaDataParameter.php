<?php

namespace IMEdge\RpcApi\Reflection;

use JsonSerializable;
use ReflectionParameter;
use stdClass;

class MetaDataParameter implements JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $isVariadic,
        public readonly bool $isOptional,
        public ?string $description = null,
    ) {
    }

    public static function fromReflection(ReflectionParameter $ref): MetaDataParameter
    {
        $name = $ref->getName();

        return new MetaDataParameter(
            $name,
            TypeHelper::describe($ref->getType()),
            $ref->isVariadic(),
            $ref->isOptional()
        );
    }

    public function jsonSerialize(): stdClass
    {
        return (object) [
            'name' => $this->name,
            'type' => $this->type,
            'isVariadic' => $this->isVariadic,
            'isOptional' => $this->isOptional,
            'description' => $this->description,
        ];
    }
}
