<?php

namespace IMEdge\Json;

use JsonException;

use function json_decode;
use function json_encode;

class JsonString
{
    protected const DEFAULT_FLAGS = JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR;

    /**
     * Encode with well-known flags, as we require the result to be reproducible
     * @throws JsonException
     */
    public static function encode($mixed, ?int $flags = null): string
    {
        return json_encode($mixed, self::DEFAULT_FLAGS | $flags);
    }

    /**
     * Decode the given JSON string and make sure we get a meaningful Exception
     *
     * @return mixed
     * @throws JsonException
     */
    public static function decode(string $string)
    {
        return json_decode($string, null, 512, JSON_THROW_ON_ERROR);
    }

    public static function decodeOptional(?string $string)
    {
        if ($string === null) {
            return null;
        }

        return static::decode($string);
    }
}
