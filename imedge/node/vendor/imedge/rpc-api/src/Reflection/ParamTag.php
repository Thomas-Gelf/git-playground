<?php

namespace IMEdge\RpcApi\Reflection;

use RuntimeException;

class ParamTag extends Tag
{
    public string $name;
    public string $dataType;
    public ?string $description = null;
    public bool $isVariadic = false;

    protected function parseTagValue(string $value): void
    {
        $parts = preg_split('/(\s+)/us', $value, 3, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new RuntimeException("Failed to split tag value: $value");
        }
        if (!str_starts_with($parts[0], '$') && !str_starts_with($parts[0], '...$')) {
            $type = array_shift($parts);
            if ($type === null) {
                throw new RuntimeException('Failed to shift tag parts');
            }
            $this->dataType = $type;
            array_shift($parts);
        }
        if (empty($parts)) {
            return;
        }

        if (str_starts_with($parts[0], '$')) {
            $this->name = substr($parts[0], 1);
            array_shift($parts);
            array_shift($parts);
        } elseif (!str_starts_with($parts[0], '...$')) {
            $this->name = substr($parts[0], 4);
            $this->isVariadic = true;
            array_shift($parts);
            array_shift($parts);
        }

        if (! empty($parts)) {
            $this->description = implode($parts);
        }
    }
}
