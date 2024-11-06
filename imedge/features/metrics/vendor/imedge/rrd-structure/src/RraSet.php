<?php

namespace IMEdge\RrdStructure;

use IMEdge\Json\JsonSerialization;
use InvalidArgumentException;
use RuntimeException;

use function explode;
use function implode;

class RraSet implements JsonSerialization
{
    /** @var Rra[] */
    protected array $rras = [];

    /**
     * @param array<Rra|string> $rras
     */
    public function __construct(array $rras)
    {
        foreach ($rras as $rra) {
            if ($rra instanceof Rra) {
                $this->rras[] = $rra;
            } else {
                $this->rras[] = Rra::fromString($rra);
            }
        }
    }

    public static function fromString(string $str): RraSet
    {
        return new RraSet(explode(' ', $str));
    }

    /**
     * @return Rra[]
     */
    public function getRras(): array
    {
        return $this->rras;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return implode(' ', $this->rras);
    }

    public function getDataSize(): int
    {
        $size = 0;
        foreach ($this->rras as $rra) {
            $size += $rra->getDataSize();
        }

        return $size;
    }

    public function getRraByIndex(int $index): Rra
    {
        if (isset($this->rras[$index])) {
            return $this->rras[$index];
        } else {
            throw new InvalidArgumentException("There is no RRA at index '$index'");
        }
    }

    public function getLongestRra(): Rra
    {
        return $this->getRraByIndex($this->getIndexForLongestRra());
    }

    public function getIndexForLongestRra(): int
    {
        $maxPdp = 0;
        $rraIdx = 0;
        // $oldestPossible = 0;
        /** @var Rra $rra */
        foreach ($this->rras as $idx => $rra) {
            // TODO: what about RraForecasting?
            if ($rra instanceof RraAggregation) {
                $curPdp = $rra->getRows() * $rra->getSteps();
                if ($curPdp > $maxPdp) {
                    $maxPdp = $curPdp;
                    $rraIdx = $idx;
                }
            }
        }

        return $rraIdx;
    }

    /**
     * @param string|string[]|mixed $any
     * @return RraSet
     */
    public static function fromSerialization($any): RraSet
    {
        if (is_array($any)) {
            return new RraSet($any);
        } elseif (is_string($any)) {
            return RraSet::fromString($any);
        }

        throw new RuntimeException(sprintf('Cannot un-serialize %s into RraSet', get_debug_type($any)));
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }
}
