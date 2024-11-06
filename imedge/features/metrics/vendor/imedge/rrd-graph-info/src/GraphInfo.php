<?php

namespace IMEdge\RrdGraphInfo;

use IMEdge\Json\JsonSerialization;
use IMEdge\RrdStructure\NumericValue;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function explode;
use function preg_match;
use function strpos;
use function strtolower;
use function substr;

/**
 * graphv gives:
 * graph_left = 83
 * graph_top = 15
 * graph_width = 742
 * graph_height = 288
 * image_width = 840
 * image_height = 320
 * graph_start = 1493928095
 * graph_end = 1493942495
 * value_min = 0,0000000000e+00
 * value_max = 1,4626943333e+00
 * image = BLOB_SIZE:103461
 */
class GraphInfo implements JsonSerialization
{
    /** @var string[] */
    public array $legend = [];
    /** @var array<int|string, string|float|int|null> key is 'string' after print label replacement */
    public array $print = [];
    public int $headerLength = 0;
    public int $imageSize = 0;
    public ?GraphInfoGraph $graph = null;
    public ?GraphInfoImage $image = null;
    public ?GraphInfoValue $value = null;
    public float $timeSpent;

    /** @var string svg|png */
    public string $format;

    /** @var string e.g. image\/svg+xml */
    public string $type;

    /** @var string data:image\/svg+xml;utf8,%3C?xml... */
    public string $raw;

    public function jsonSerialize(): object
    {
        return (object) [
            'legend'         => $this->legend,
            'print'          => $this->print,
            'headerLength'   => $this->headerLength,
            'imageSize'      => $this->imageSize,
            'graph'          => $this->graph,
            'image'          => $this->image,
            'value'          => $this->value,
            'timeSpent'      => $this->timeSpent,
            'format'         => $this->format,
            'type'           => $this->type,
            'raw'            => $this->raw,
        ];
    }

    public static function parse(
        string $image,
        string $format,
        float $timeSpentTotal,
        LoggerInterface $logger
    ): GraphInfo {
        // This is what we're going to parse:
        /*
        graph_left = 39
        graph_top = 15
        graph_width = 1546
        graph_height = 680
        image_width = 1600
        image_height = 400
        graph_start = 1501538400
        graph_end = 1533564000
        value_min = 0,0000000000e+00
        value_max = 1,0647666667e+01
        image = BLOB_SIZE:1229123
         */

        $info = new GraphInfo(); // TODO?
        $info->timeSpent = $timeSpentTotal;
        $pos = 0;
        $blobSize = null;
        $nsProperties = [
            'graph' => [],
            'image' => [],
            'value' => [],
        ];
        while ($blobSize === null) {
            $newLine = strpos($image, "\n", $pos);
            if ($newLine === false) {
                throw new RuntimeException(
                    "Unable to parse rrdgraph info, there is no more newline after char #$pos"
                );
            }
            $line = substr($image, $pos, $newLine - $pos);
            //$logger->notice('IMAGE: ' . $line);
            // $props['lines'][] = $line; - debug only
            $pos = $newLine + 1;
            if (preg_match('/^([a-z_]+)\s=\s(.+)$/', $line, $match)) {
                $key = $match[1];
                if ($key === 'image') {
                    // image = BLOB_SIZE:1229123
                    $blobSize = (int)\preg_replace('/^BLOB_SIZE:/', '', $match[2]);
                    break;
                } else {
                    list($ns, $relKey) = explode('_', $key, 2);
                }
                switch ($ns) {
                    case 'graph':
                    case 'image':
                        $value = (int)$match[2];
                        break;
                    case 'value':
                        $value = NumericValue::parseLocalizedFloat($match[2]);
                        break;
                    default:
                        $value = $match[2];
                }
                if (isset($nsProperties[$ns])) {
                    $nsProperties[$ns][$relKey] = $value;
                }
            } elseif (preg_match('/^print\[(\d+)]\s=\s(.+)$/', $line, $match)) {
                $key = (int) $match[1];
                $value = $match[2];
                if (preg_match('/^"(-?\d+)([,.]\d+)?"$/', $value, $match)) {
                    if (isset($match[2])) {
                        $value = NumericValue::parseLocalizedFloat($match[1] . $match[2]);
                    } else {
                        $value = (int) $match[1];
                    }
                }
                $info->print[$key] = $value;
            } elseif (preg_match('/^legend\[(\d+)]\s=\s(.+)$/', $line, $match)) {
                $key = $match[1];
                $value = $match[2];
                $info->legend[$key] = $value;
            } elseif (preg_match('/^coords\[(\d+)]\s=\s(.+)$/', $line, $match)) {
                $key = $match[1];
                $value = $match[2];
                $props['coords'][$key] = array_map('intval', explode(',', $value));
            } else {
                throw new RuntimeException("Unable to parse rrdgraph info line: '$line'");
            }
        }
        if (! empty($nsProperties['image'])) {
            /** @var object{width: int, height: int} $any */
            $any = (object) $nsProperties['image'];
            $info->image = GraphInfoImage::fromSerialization($any);
        }
        if (! empty($nsProperties['graph'])) {
            /** @var object{left: int, top: int, width: int, height: int, start: int, end: int} $any */
            $any = (object) $nsProperties['graph'];
            $info->graph = GraphInfoGraph::fromSerialization($any);
        }
        if (! empty($nsProperties['value'])) {
            /** @var object{min: int, max: int} $any */
            $any = (object) $nsProperties['value'];
            $info->value = GraphInfoValue::fromSerialization($any);
        }
        $info->headerLength = $pos;
        $info->imageSize = $blobSize;
        $info->format = strtolower($format);
        $info->type = ImageHelper::getContentTypeForFormat($format); // TODO ->contentType
        $info->raw = ImageHelper::createInlineImage(substr($image, $info->headerLength), $info->type);

        return $info;
    }

    /**
     * @param object{
     *     legend: string[],
     *     print: array<int|string, string|float|int|null>,
     *     imageSize: int,
     *     headerLength: int,
     *     format: string,
     *     type: string,
     *     raw: string,
     *     timeSpent: float,
     *     graph: object{left: int, top: int, width: int, height: int, start: int, end: int}|null,
     *     image: object{width: int, height: int}|null,
     *     value: object{min: int, max: int}|null
     * } $any
     * @return GraphInfo
     */
    public static function fromSerialization($any): GraphInfo
    {
        $self = new GraphInfo();
        $self->legend = (array) $any->legend;
        $self->print = (array) $any->print;
        $self->imageSize = $any->imageSize;
        $self->headerLength = $any->headerLength;
        $self->format = $any->format;
        $self->type = $any->type;
        $self->raw = $any->raw;
        $self->timeSpent = $any->timeSpent;
        if (isset($any->graph)) {
            $self->graph = GraphInfoGraph::fromSerialization($any->graph);
        }
        if (isset($any->image)) {
            $self->image = GraphInfoImage::fromSerialization($any->image);
        }
        if (isset($any->value)) {
            $self->value = GraphInfoValue::fromSerialization($any->value);
        }

        return $self;
    }
}
