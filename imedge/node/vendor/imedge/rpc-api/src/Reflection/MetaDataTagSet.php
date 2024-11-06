<?php

namespace IMEdge\RpcApi\Reflection;

class MetaDataTagSet
{
    /** @var Tag[] */
    protected array $tags;

    public function __construct()
    {
        $this->tags = [];
    }

    public function add(Tag $tag): void
    {
        $this->tags[] = $tag;
    }

    public function byType(string $type): MetaDataTagSet
    {
        $set = new MetaDataTagSet();
        foreach ($this->tags as $tag) {
            if ($tag->tagType === $type) {
                $set->add($tag);
            }
        }

        return $set;
    }

    /**
     * @return ParamTag[]
     */
    public function getParams(): array
    {
        $result = [];
        foreach ($this->byType('param')->getTags() as $tag) {
            assert($tag instanceof ParamTag);
            $result[] = $tag;
        }

        return $result;
    }

    public function getReturnType(): ?string
    {
        foreach ($this->byType('return')->getTags() as $tag) {
            assert($tag instanceof ReturnTag);
            // TODO: return a class, we need the description. Done?
            return $tag->dataType;
        }

        return null;
    }

    /**
     * @return Tag[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}
