<?php

namespace IMEdge\Metrics;

use IMEdge\Json\JsonSerialization;
use RuntimeException;

class Ci implements JsonSerialization
{
    /**
     * @param array<string, int|string>|null $tags
     */
    final public function __construct(
        public readonly ?string $hostname = null,
        public readonly ?string $subject = null,
        public readonly ?string $instance = null,
        public readonly ?array $tags = []
    ) {
    }

    public static function fromSerialization($any): Ci
    {
        if (! is_array($any)) {
            throw new RuntimeException(sprintf(
                '%s::%s() expects an array, got %s',
                __CLASS__,
                __METHOD__,
                get_debug_type($any)
            ));
        }
        if (isset($any[3])) {
            $any[3] = (array) $any[3];
        }

        return new static(...$any);
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: array<string, int|string>|null}
     */
    public function jsonSerialize(): array
    {
        return [$this->hostname, $this->subject, $this->instance, $this->tags];
    }
}
