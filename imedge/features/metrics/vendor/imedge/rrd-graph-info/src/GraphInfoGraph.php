<?php

namespace IMEdge\RrdGraphInfo;

use IMEdge\Json\JsonSerialization;

class GraphInfoGraph implements JsonSerialization
{
    protected int $left;
    protected int $top;
    protected int $width;
    protected int $height;
    protected int $start;
    protected int $end;

    public function __construct(int $left, int $top, int $width, int $height, int $start, int $end)
    {
        $this->left = $left;
        $this->top = $top;
        $this->width = $width;
        $this->height = $height;
        $this->start = $start;
        $this->end = $end;
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'left'   => $this->left,
            'top'    => $this->top,
            'width'  => $this->width,
            'height' => $this->height,
            'start'  => $this->start,
            'end'    => $this->end,
        ];
    }

    /**
     * @param object{left: int, top: int, width: int, height: int, start: int, end: int} $any
     * @return GraphInfoGraph
     */
    public static function fromSerialization($any): GraphInfoGraph
    {
        return new GraphInfoGraph(
            $any->left,
            $any->top,
            $any->width,
            $any->height,
            $any->start,
            $any->end
        );
    }
}
