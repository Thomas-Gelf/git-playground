<?php

namespace IMEdge\RpcApi\Reflection;

class TypeDescriptionTag extends Tag
{
    public string $dataType;
    public ?string $description = null;

    protected function parseTagValue(string $value): void
    {
        if (empty($value)) {
            return;
        }
        $parts = preg_split('/(\s+)/us', trim($value), 2, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new \RuntimeException('Failed to split tag value');
        }
        $this->dataType = array_shift($parts) ?? throw new \RuntimeException('Cannot shift tag value part');
        array_shift($parts);

        if (empty($parts)) {
            return;
        }

        $this->description = implode($parts);
    }
}
