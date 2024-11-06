<?php

namespace IMEdge\Log\Formatter;

use function date;
use function microtime;

class StdOutFormatter implements LogFormatter
{
    protected string $dateFormat = 'Y-m-d H:i:s';
    protected bool $showTimestamp = true;

    public function format(string $level, string $message, array $context = []): string
    {
        if (empty($context)) {
            return $this->renderDatePrefix() . $message;
        }

        return $this->renderDatePrefix() . sprintf($message, ...$context);
    }

    protected function renderDatePrefix(): string
    {
        if ($this->showTimestamp) {
            return date($this->dateFormat, microtime(true));
        }

        return '';
    }

    public function setShowTimestamp(bool $show = true): void
    {
        $this->showTimestamp = $show;
    }
}
