<?php

namespace IcingaDataNode\Monitoring;

use gipfl\Json\JsonSerialization;
use gipfl\Json\JsonString;
use InvalidArgumentException;
use RuntimeException;

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

    public function setGaugeValue(string $name, $value, ?string $unit = null): void
    {
        $this->getMetric($name, MetricDatatype::GAUGE, $unit)->value = $value;
    }

    public function incrementCounter(string $name, float|int $increment = 1): void
    {
        $metric = $this->getMetric($name, MetricDatatype::COUNTER);
        $metric->value = $metric->value + $increment;
    }

    /**
     * @return Metric[]
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getMetric(string $name, ?MetricDatatype $type = null, ?string $unit = null): Metric
    {
        if (isset($this->metrics[$name])) {
            // TODO: adapt unit?!
            if ($this->metrics[$name]->type !== $type) {
                throw new RuntimeException(sprintf(
                    '%s requested for "%s", got %s',
                    $type->value,
                    $name,
                    $this->metrics[$name]->type->value
                ));
            }
            return $this->metrics[$name];
        }

        return $this->metrics[$name] = new Metric($name, $type, $unit);
    }

    public function resetCounters(float|int|null $resetTo = null): void
    {
        foreach ($this->metrics as $metric) {
            if ($metric->type === MetricDatatype::COUNTER) {
                $metric->value = $resetTo;
            }
        }
    }

    public function reset(int|float|null $resetTo = null): void
    {
        foreach ($this->metrics as $metric) {
            $metric->value = $resetTo;
        }
    }

    public function has(string $label): bool
    {
        return isset($this->metrics[$label]);
    }

    public function requireMetric(string $label): Metric
    {
        if (isset($this->metrics[$label])) {
            return $this->metrics[$label];
        }

        throw new InvalidArgumentException("There is no such DataPoint: '$label'");
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

    public function jsonSerialize(): array
    {
        return [
            JsonString::encode($this->ci),
            $this->timestamp,
            $this->metrics, // array_values?
        ];
    }

    public static function fromSerialization($any): self
    {
        return new static(
            Ci::fromSerialization(JsonString::decode($any[0])),
            $any[1] ?? null,
            array_map(Metric::fromSerialization(...), (array) $any[2])
        );
    }
}
