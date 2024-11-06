<?php

namespace IMEdge\RpcApi\Reflection;

use IMEdge\RpcApi\ApiMethod;
use IMEdge\RpcApi\ApiMethodType;
use IMEdge\RpcApi\ApiParameter;
use ReflectionNamedType;

class MetaDataMethod
{
    public ?string $returnType = null;

    /** @var MetaDataParameter[] */
    public array $parameters = [];
    /** @var MetaDataParameter[] */
    protected array $parametersByName = [];
    public ?string $title = null;
    public ?string $description = null;

    public function __construct(
        public readonly string $name,
        // Either 'request' or 'notification'
        public readonly ApiMethodType $type
    ) {
    }

    public function getParameter(int|string $key): ?MetaDataParameter
    {
        if (is_int($key)) {
            return $this->parameters[$key] ?? null;
        }

        return $this->parametersByName[$key] ?? null;
    }

    public static function fromReflection(\ReflectionMethod $method): ?MetaDataMethod
    {
        foreach ($method->getAttributes(ApiMethod::class) as $apiMethod) {
            $instance = $apiMethod->newInstance();
            $parameters = [];
            $parameterIndex = [];
            foreach ($method->getParameters() as $parameter) {
                $parameterMeta = null;
                $parameterMetaReflected = MetaDataParameter::fromReflection($parameter);
                foreach ($parameter->getAttributes(ApiParameter::class) as $apiParameter) {
                    $parameterMeta = $apiParameter->newInstance()->parameter;
                    if ($description = $parameterMetaReflected->description) {
                        $parameterMeta->description = $description;
                    }
                }
                if ($parameterMeta === null) {
                    $parameterMeta = $parameterMetaReflected;
                }
                $parameterIndex[$parameterMeta->name] = $parameterMeta;
                $parameters[] = $parameterMeta;
            }

            $docComment = $method->getDocComment();
            if ($docComment === false) {
                $docComment = '';
            }
            $parser = MethodCommentParser::parseComment($docComment);
            if (
                ($type = $method->getReturnType())
                && ($type instanceof ReflectionNamedType)
                && ($type->getName() === 'void')
            ) {
                $methodType = ApiMethodType::NOTIFICATION;
            } else {
                $methodType = ApiMethodType::REQUEST;
            }
            $meta = new MetaDataMethod($instance->name ?? $method->getName(), $methodType);
            if ($betterReturnType = $parser->getReturnType()) {
                $meta->returnType = $betterReturnType;
            } else {
                $meta->returnType = TypeHelper::describe($method->getReturnType());
            }
            foreach ($parser->getParams() as $parameter) {
                // TODO: pick more details? array specs?
                if (isset($parameterIndex[$parameter->name])) {
                    $parameterIndex[$parameter->name]->description = $parameter->description;
                }
            }
            $meta->parameters = $parameters;
            $meta->parametersByName = $parameterIndex;
            $meta->title = $parser->getTitle();
            $meta->description = $parser->getDescription();

            return $meta;
        }

        return null;
    }

    public function addParameter(MetaDataParameter $parameter): void
    {
        $this->parameters[$parameter->name] = $parameter;
    }

    /*
    public function getParameter(string $name): MetaDataParameter
    {
        return $this->parameters[$name]
            ?? throw new InvalidArgumentException("There is no '$name' parameter" . print_r($this->parameters, true));
    }
    */

    /**
     * @return string
     */
    public function getReturnType(): string
    {
        return $this->returnType ?: 'void';
    }

    /**
     * @return MetaDataParameter[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
