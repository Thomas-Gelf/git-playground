<?php

namespace IMEdge\RrdGraphInfo;

use IMEdge\Json\JsonSerialization;

class GraphInfoImage implements JsonSerialization
{
    public int $width;
    public int $height;

    public function __construct(int $width, int $height)
    {
        $this->height = $height;
        $this->width = $width;
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'width'  => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * @param object{width: int, height: int} $any
     * @return GraphInfoImage
     */
    public static function fromSerialization($any): GraphInfoImage
    {
        return new GraphInfoImage($any->width, $any->height);
    }
}
