<?php

namespace IcingaDataNode\Monitoring;

use gipfl\Json\JsonSerialization;

class Ci implements JsonSerialization
{
    public function __construct(
        public readonly ?string $hostname = null,
        public readonly ?string $subject = null,
        public readonly ?string $instance = null,
        public readonly ?array $tags = []
    ) {
    }

    public static function fromSerialization($any): Ci
    {
        return new static(...$any);
    }

    public function jsonSerialize(): array
    {
        return [$this->hostname, $this->subject, $this->instance, $this->tags];
    }
}
