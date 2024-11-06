<?php

namespace IMEdge\RpcApi\Reflection;

use IMEdge\RpcApi\ApiNamespace;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

class MetaDataClass
{
    public string $namespace;
    /** @var array<string, MetaDataMethod> */
    public array $methods = [];
    public ?string $error = null;

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function analyze(string $class): ?MetaDataClass
    {
        $classMeta = new MetaDataClass();
        $ref = new ReflectionClass($class);
        $namespace = null;
        foreach ($ref->getAttributes(ApiNamespace::class) as $ns) {
            $namespace = $ns->newInstance()->name;
        }
        if ($namespace === null) {
            return null;
        }
        $classMeta->namespace = $namespace;

        foreach ($ref->getMethods() as $method) {
            if ($methodMeta = MetaDataMethod::fromReflection($method)) {
                $classMeta->addMethod($methodMeta);
            }
        }

        return $classMeta;
    }

    public function addMethod(MetaDataMethod $method): void
    {
        $name = $method->name;
        if (isset($this->methods[$name])) {
            throw new InvalidArgumentException("Cannot add method '$name' twice");
        }

        $this->methods[$name] = $method;
    }

    public function getMethod(string $name): MetaDataMethod
    {
        return $this->methods[$name]
            ?? throw new InvalidArgumentException("There is no '$name' method");
    }
}
