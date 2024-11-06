<?php

namespace IMEdge\RpcApi;

use Closure;
use IMEdge\Json\JsonSerialization;
use InvalidArgumentException;

class Hydrator
{
    /** @var array<string, Closure>  */
    protected static array $registeredTypes = [];

    public static function registerType(string $type, Closure $hydrator): void
    {
        self::$registeredTypes['\\' . ltrim($type, '\\')] = $hydrator;
    }

    /**
     * @template T of JsonSerialization
     * @param class-string<T> $type
     * @param mixed $value
     * @return T
     */
    protected static function classInstance(string $type, mixed $value): JsonSerialization
    {
        $implements = class_implements($type);

        if (is_array($implements)) {
            if (in_array(JsonSerialization::class, $implements, true)) {
                return $type::fromSerialization($value);
            }
        }

        throw self::castError($type, $value);
    }

    protected static function castError(string $expected, mixed $value): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('Cannot cast %s to %s', get_debug_type($value), $expected));
    }

    public static function hydrate(string $type, mixed $value): mixed
    {
        if (str_starts_with($type, '?')) {
            if ($value === null) {
                return null;
            }

            $type = substr($type, 1);
        }
        if (str_ends_with($type, '|null')) {
            if ($value === null) {
                return null;
            }

            $type = substr($type, 0, -5);
        }

        if ($value === null) {
            return new InvalidArgumentException(sprintf('Cannot cast null to %s', $type));
        }

        if (str_ends_with($type, '[]')) {
            $type = substr($type, 0, -2);
            $result = [];
            foreach ((array) $value as $entry) {
                $result[] = self::hydrate($type, $entry);
            }

            return $result;
        }

        switch ($type) {
            case 'int':
                if (is_int($value)) {
                    return $value;
                }
                if (is_string($value)) {
                    return (int) $value;
                }

                throw self::castError('int', $value);
            case 'float':
                if (is_float($value)) {
                    return $value;
                }
                if (is_int($value)) {
                    return (float) $value;
                }
                if (is_string($value)) {
                    return (int) $value;
                }

                throw self::castError('float', $value);
            case 'float|int':
                if (is_int($value) || is_float($value)) {
                    return $value;
                }
                if (is_string($value)) {
                    if (preg_match('/^-?\d+$/', $value)) {
                        return (int) $value;
                    }

                    return (float) $value;
                }

                throw self::castError('float|int', $value);
            case 'string':
                if (is_string($value)) {
                    return $value;
                }

                if (is_int($value) || is_float($value)) {
                    return (string) $value;
                }

                throw self::castError('string', $value);
            case 'array':
                return (array) $value;
            case 'bool':
                return (bool) $value;
            case 'object':
                return (object) $value;
            default:
                if (isset(self::$registeredTypes[$type])) {
                    $hydrator = self::$registeredTypes[$type];
                    return $hydrator($value);
                }
                if (class_exists($type)) {
                    return self::classInstance($type, $value);
                }

                throw new InvalidArgumentException(sprintf(
                    'Unsupported parameter type: %s',
                    $type,
                ));
        }
    }
}
