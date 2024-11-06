<?php

namespace IMEdge\Log;

use IMEdge\Log\Filter\LogFilter;
use IMEdge\Log\Writer\LogWriter;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

use function array_values;
use function spl_object_hash;

class Logger implements LoggerInterface
{
    use LoggerTrait;

    protected array $writers = [];

    /** @var LogFilter[] */
    protected array $filters = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (! $this->wants($level, $message, $context)) {
            return;
        }

        foreach ($this->writers as $writer) {
            $writer->write($level, $message, $context);
        }
    }

    public function addWriter(LogWriter $writer): void
    {
        $this->writers[spl_object_hash($writer)] = $writer;
    }

    public function addFilter(LogFilter $filter): void
    {
        $this->filters[spl_object_hash($filter)] = $filter;
    }

    /**
     * @return LogWriter[]
     */
    public function getWriters(): array
    {
        return array_values($this->writers);
    }

    /**
     * @return LogFilter[]
     */
    public function getFilters(): array
    {
        return array_values($this->filters);
    }

    public function removeWriter(LogWriter $writer): void
    {
        unset($this->filters[spl_object_hash($writer)]);
    }

    public function removeFilter(LogFilter $filter): void
    {
        unset($this->filters[spl_object_hash($filter)]);
    }

    protected function wants($level, string|\Stringable $message, array $context = []): bool
    {
        foreach ($this->filters as $filter) {
            if (! $filter->wants($level, $message, $context)) {
                return false;
            }
        }

        return true;
    }
}
