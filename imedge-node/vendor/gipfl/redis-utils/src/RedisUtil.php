<?php

namespace gipfl\RedisUtils;

class RedisUtil
{
    /**
     * Transform [key1, val1, key2, val2] into {key1 => val1, key2 => val2}
     *
     * @param $data
     * @return object
     */
    public static function makeHash($data)
    {
        if ($data === null) {
            return null;
        }

        return (object) static::makeArray($data);
    }

    /**
     * Transform [key1, val1, key2, val2] into [key1 => val1, key2 => val2]
     *
     * @param $data
     * @return array
     */
    public static function makeArray($data)
    {
        if ($data === null) {
            return [];
        }
        $array = [];
        $count = count($data);
        for ($i = 0; $i < $count; $i += 2) {
            $array[$data[$i]] = $data[$i + 1];
        }

        return $array;
    }
}
