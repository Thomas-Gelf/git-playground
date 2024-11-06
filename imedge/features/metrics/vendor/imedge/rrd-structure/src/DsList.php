<?php

namespace IMEdge\RrdStructure;

use IMEdge\Json\JsonSerialization;
use InvalidArgumentException;
use RuntimeException;

use function implode;
use function array_keys;

class DsList implements JsonSerialization
{
    /** @var Ds[] */
    protected array $list = [];
    /** @var array<string, string> Alias => Name */
    protected array $aliasMap = [];

    /**
     * @param Ds[] $list
     */
    public function __construct(array $list = [])
    {
        foreach ($list as $ds) {
            $this->add($ds);
        }
    }

    public function add(Ds $ds): void
    {
        $this->list[$ds->getName()] = $ds;
        if ($alias = $ds->getAlias()) {
            $this->aliasMap[$alias] = $ds->getName();
        }
    }

    public function requireDs(string $name): Ds
    {
        if (isset($this->list[$name])) {
            return $this->list[$name];
        }

        throw new InvalidArgumentException("There is no such DS: '$name'");
    }

    public static function fromString(string $str): DsList
    {
        $self = new DsList();
        foreach (explode(' ', $str) as $ds) {
            $self->add(Ds::fromString($ds));
        }

        return $self;
    }

    /**
     * @return Ds[]
     */
    public function getDataSources(): array
    {
        return $this->list;
    }

    /**
     * @return string[]
     */
    public function listNames(): array
    {
        return array_keys($this->list);
    }

    public function hasName(string $name): bool
    {
        return isset($this->list[$name]);
    }

    /**
     * @return string[]
     */
    public function listAliases(): array
    {
        return array_keys($this->aliasMap);
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->aliasMap[$alias]);
    }

    /**
     * @param array<string, string> $map
     */
    public function applyAliasesMap(array $map): void
    {
        $this->aliasMap = [];
        foreach ($map as $alias => $name) {
            if (isset($this->list[$name])) {
                $this->list[$name]->setAlias($alias);
                $this->aliasMap[$alias] = $name;
            } else {
                throw new RuntimeException('There is no "%s" in this DsList: ' . $name);
            }
        }
    }

    public function applyAliasMapFromDsList(DsList $dsList): void
    {
        $this->applyAliasesMap($dsList->getAliasesMap());
    }

    /**
     * @return array<string, string>
     */
    public function getAliasesMap(): array
    {
        return $this->aliasMap;
    }

    /**
     * @return string[]
     */
    public function listDsWithoutAlias(): array
    {
        $list = [];
        foreach ($this->list as $ds) {
            if (null === $ds->getAlias()) {
                $list[] = $ds->getName();
            }
        }

        return $list;
    }

    public function __toString(): string
    {
        return implode(' ', $this->list);
    }

    /**
     * @param array<object{
     *      name: string,
     *      type: string,
     *      heartbeat: int,
     *      min: ?int,
     *      max: ?int,
     *      mappedName: ?string,
     *      alias: ?string,
     *  }> $any
     * @return DsList
     */
    public static function fromSerialization($any): DsList
    {
        $dsList = [];
        foreach ((array) $any as $ds) {
            $dsList[] = Ds::fromSerialization($ds);
        }
        return new DsList($dsList);
    }

    /**
     * @return Ds[]
     */
    public function jsonSerialize(): array
    {
        return $this->list;
    }
}
