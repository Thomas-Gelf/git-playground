<?php

namespace IMEdge\RpcApi\Reflection;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;

class TypeHelper
{
    public static function describe(?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'null';
        }

        if ($type instanceof ReflectionUnionType) {
            $typeList = [];
            $hasNull = false;
            foreach ($type->getTypes() as $subType) {
                /** @var ReflectionNamedType $subType */
                if ($subType->getName() === 'null') {
                    $hasNull = true;
                    continue;
                }
                $typeList[] = $subType->getName();
            }

            sort($typeList);
            if (count($typeList) === 1 && $hasNull) {
                return $typeList[0] . '|null';
            }
            if ($typeList === ['float', 'int']) {
                return 'float|int' . ($hasNull ? '|null' : '');
            }

            throw new RuntimeException('Union types are not supported: ' . implode(', ', $typeList));
        } elseif ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if (! $type->isBuiltin()) {
                $name = '\\' . ltrim($name, '\\');
            }

            return $name;
        } elseif ($type instanceof ReflectionIntersectionType) {
            $typeList = [];
            foreach ($type->getTypes() as $subType) {
                /** @var ReflectionNamedType $subType */
                $typeList[] = $subType->getName();
            }
            throw new RuntimeException('Reflection types are not supported: ' . implode(', ', $typeList));
        } else {
            throw new RuntimeException('ReflectionType ' . get_class($type) . ' is not supported');
        }
    }
}
