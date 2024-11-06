<?php

namespace IMEdge\IcingaPerfData;

class PerfDataStringList
{
    protected int $pos = 0;

    protected string $string;

    protected int $length;

    /**
     * @return array<string, string>
     */
    public static function split(string $string): array
    {
        $self = new PerfDataStringList($string);
        return $self->extractParts();
    }

    protected function __construct(string $string)
    {
        $this->string = trim($string);
        $this->length = strlen($string);
    }

    /**
     * @return array<string, string>
     */
    protected function extractParts(): array
    {
        $this->pos = 0;
        $list = [];

        while ($this->pos < $this->length) {
            $label = trim($this->readLabel());
            $value = trim($this->readUntil(' '));

            if (strlen($label) > 0) {
                $list[$label] = $value;
            }
        }

        return $list;
    }

    protected function readLabel(): string
    {
        $this->skipSpaces();
        $currentCharacter = $this->string[$this->pos];
        if ($currentCharacter === '"' || $currentCharacter === "'") {
            $quote = $currentCharacter;
            $this->pos++;
            $label = $this->readUntil('=');
            $this->pos++;
            $label = rtrim($label, $quote);
        } else {
            $label = $this->readUntil('=');
            $this->pos++;
        }
        $this->skipSpaces();

        return $label;
    }

    protected function readUntil(string $char): string
    {
        $start = $this->pos;
        while ($this->pos < $this->length && $this->string[$this->pos] !== $char) {
            $this->pos++;
        }

        return substr($this->string, $start, $this->pos - $start);
    }

    protected function skipSpaces(): void
    {
        while ($this->pos < $this->length && $this->string[$this->pos] === ' ') {
            $this->pos++;
        }
    }
}
