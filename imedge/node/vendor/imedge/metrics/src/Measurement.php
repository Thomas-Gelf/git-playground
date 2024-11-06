<?php

namespace IMEdge\Metrics;

use IMEdge\Json\JsonSerialization;
use IMEdge\Json\JsonString;
use JsonException;

class Measurement implements JsonSerialization
{
    public readonly Ci $ci;
    protected ?int $timestamp;
    /** @var Metric[] */
    protected array $metrics = [];

    /**
     * @param Metric[]|null $metrics
     */
    public function __construct(Ci $ci, ?int $timestamp = null, ?array $metrics = [])
    {
        $this->ci = $ci;
        $this->timestamp = $timestamp;
        if ($metrics !== null) {
            foreach ($metrics as $metric) {
                $this->addMetric($metric);
            }
        }
    }

    /**
     * @return Metric[]
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function addMetric(Metric $metric): void
    {
        $this->metrics[$metric->label] = $metric;
    }

    public function countMetrics(): int
    {
        return count($this->metrics);
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    /**
     * @return array{string, ?int, Metric[]}
     * @throws JsonException
     */
    public function jsonSerialize(): array
    {
        return [
            JsonString::encode($this->ci),
            $this->timestamp,
            $this->metrics, // array_values, ksort?
        ];
    }

    public static function fromSerialization($any): Measurement
    {
        return new Measurement(
            Ci::fromSerialization(JsonString::decode($any[0])),
            $any[1] ?? null,
            array_map(Metric::fromSerialization(...), (array) $any[2])
        );
    }
}
