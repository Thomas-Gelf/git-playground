<?php

namespace IMEdge\RrdStructure;

use IMEdge\Json\JsonSerialization;
use InvalidArgumentException;
use RuntimeException;

use function ctype_digit;
use function explode;
use function is_numeric;
use function preg_match;
use function preg_split;
use function str_replace;
use function strlen;
use function strpos;
use function substr;
use function trim;

// rrdtool create filename [--start|-b start time] [--step|-s step] [--template|-t template-file]
// [--source|-r source-file] [--no-overwrite|-O] [--daemon|-d address]
// [DS:ds-name[=mapped-ds-name[[source-index]]]:DST:dst arguments]
// [RRA:CF:cf arguments]
class RrdInfo implements JsonSerialization
{
    protected const FORMAT_RRDTOOL = 1;
    protected const FORMAT_RRDCACHED = 2;

    protected string $filename;
    protected int $step;
    protected DsList $dsList;
    protected RraSet $rra;
    protected ?string $rrdVersion = null; // e.g. '0003'
    protected ?int $headerSize = null;
    protected ?int $lastUpdate = null;

    public function __construct(string $filename, int $step, DsList $dsList, RraSet $rra)
    {
        $this->filename = $filename;
        $this->step = $step;
        $this->dsList = $dsList;
        $this->rra = $rra;
    }

    public function setHeaderSize(int $size): void
    {
        $this->headerSize = $size;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getRrdVersion(): ?string
    {
        return $this->rrdVersion;
    }

    public function getStep(): int
    {
        return $this->step;
    }

    public function getLastUpdate(): ?int
    {
        return $this->lastUpdate;
    }

    public function getDsList(): DsList
    {
        return $this->dsList;
    }

    /**
     * @return string[]
     */
    public function listDsNames(): array
    {
        return $this->dsList->listNames();
    }

    public function getRraSet(): RraSet
    {
        return $this->rra;
    }

    public function countDataSources(): int
    {
        return count($this->dsList->listNames());
    }

    public function getHeaderSize(): ?int
    {
        return $this->headerSize;
    }

    public function getDataSize(): int
    {
        return (int) ($this->rra->getDataSize() * $this->countDataSources());
    }

    public function getMaxRetention(): int
    {
        $rra = $this->getRraSet()->getLongestRra();
        assert($rra instanceof RraAggregation);

        return (int) ($rra->getRows() * $rra->getSteps() * $this->getStep());
    }

    public static function parse(string $string, ?string $dataDir = null): RrdInfo
    {
        $lines = preg_split('/\n/', $string, -1, PREG_SPLIT_NO_EMPTY);
        if ($lines === false) {
            throw new InvalidArgumentException('Failed to split string when parsing RrdInfo: ' . $string);
        }

        return static::parseLines($lines, $dataDir);
    }

    /**
     * @param string[] $lines
     */
    public static function parseLines(array $lines, ?string $dataDir = null): RrdInfo
    {
        return static::instanceFromParsedStructure(static::prepareStructure($lines), $dataDir);
    }

    protected static function detectLineFormat(string $line): int
    {
        return false === strpos($line, ' = ')
            ? self::FORMAT_RRDCACHED
            : self::FORMAT_RRDTOOL;
    }

    /**
     * @param string[] $lines
     * @return array<string, array<string, array<string, string|float|int|null>>>
     */
    protected static function prepareStructure(array $lines): array
    {
        $result = [];
        if (empty($lines)) {
            throw new RuntimeException('Got no info lines to parse');
        }
        $format = static::detectLineFormat($lines[0]);
        foreach ($lines as $line) {
            if ($format === self::FORMAT_RRDCACHED) {
                list($key, $value) = static::splitKeyValueFromRrdCached($line);
            } else {
                list($key, $value) = static::splitKeyValueFromRrdTool($line);
            }
            static::setArrayValue($result, $key, $value);
        }

        return $result;
    }

    /**
     * @return RrdInfo
     */
    protected static function instanceFromParsedStructure(array $array, ?string $dataDir = null): RrdInfo
    {
        $filename = $array['filename'];
        if ($dataDir) {
            $dataDir = rtrim($dataDir, '/') . '/';
            if (str_starts_with($filename, $dataDir)) {
                $filename = substr($filename, strlen($dataDir));
            }
        }

        $self = new RrdInfo(
            $filename,
            $array['step'],
            self::dsListFromArray($array['ds']),
            self::rraInfoFromArray($array['rra'])
        );
        $self->rrdVersion = $array['rrd_version'];
        $self->lastUpdate = $array['last_update'];
        $self->headerSize = $array['header_size'];

        return $self;
    }

    /**
     * Or bool|float|int|string|null?
     * @return int|float|string|null
     */
    protected static function parseValue(string $value)
    {
        if ($value === 'NaN') {
            return null;
        }

        if (strlen($value) && $value[0] === '"') {
            // TODO: unescape?
            return trim($value, '"');
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $float = self::optionallyParseFloat($value);
        if ($float === false) {
            throw new InvalidArgumentException($value . ' is not a known data type');
        }

        return $float;
    }

    /**
     * @param array<string, array{
     *      index: int,
     *      value: int|float|null,
     *      type: string,
     *      minimal_heartbeat: int,
     *      min: ?int,
     *      max: ?int,
     *      last_ds: ?string,
     *      unknown_sec: ?int
     *   }> $info
     */
    protected static function dsListFromArray(array $info): DsList
    {
        $list = new DsList();
        foreach ($info as $name => $dsInfo) {
            $list->add(DsInfo::fromArray($name, $dsInfo)->toDs());
        }

        return $list;
    }

    /**
     * @param array<array{cf: string, rows: int, xff: float, pdp_per_row: int, cur_row: ?int}> $info
     */
    protected static function rraInfoFromArray(array $info): RraSet
    {
        $rraSet = [];
        foreach ($info as $rra) {
            $rraSet[] = Rra::fromRraInfo($rra);
        }

        return new RraSet($rraSet);
    }


    /**
     * @return false|float
     */
    protected static function optionallyParseFloat(string $value)
    {
        // dirty workaround for localized numbers
        $value = str_replace(',', '.', $value);
        if (is_numeric($value)) {
            return (float) $value;
        } else {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: string|int|float|null}
     */
    protected static function splitKeyValueFromRrdTool(string $line): array
    {
        list($key, $val) = explode(' = ', $line);
        return [$key, self::parseValue($val)];
    }

    /**
     * @return array{0: string, 1: string|int|float|null}
     */
    protected static function splitKeyValueFromRrdCached(string $line): array
    {
        if (preg_match('/^(.+?)\s([012])\s(.+)$/', $line, $match)) {
            $key = $match[1];
            switch ($match[2]) {
                case '0': // float
                    if ($match[3] === 'NaN') {
                        $val = null;
                    } else {
                        $val = (float) $match[3];
                    }
                    break;
                case '1': // int
                    if ($match[3] === 'NaN') {
                        $val = null;
                    } else {
                        $val = (int) $match[3];
                    }
                    break;
                case '2': // string
                    $val = $match[3];
                    break;
                default:
                    // This is impossible because of the above regex, but makes our IDE happy:
                    throw new RuntimeException("You should never reach this point");
            }
        } else {
            throw new RuntimeException("Got invalid info line from RRDcached: $line");
        }

        return [$key, $val];
    }

    /**
     * @param array<string, array<string, array<string, string|float|int|null>>> $array
     * @param string|float|int|null $value
     */
    protected static function setArrayValue(array &$array, string $key, $value): void
    {
        if (false === ($bracket = strpos($key, '['))) {
            $array[$key] = $value;
        } else {
            $type = substr($key, 0, $bracket);
            $key = substr($key, $bracket + 1);
            $bracket = strpos($key, ']');
            if ($bracket === false) {
                throw new RuntimeException('Missing right bracket in key: ' . $key);
            }
            $idx = substr($key, 0, $bracket);
            $key = substr($key, $bracket + 2);

            // No nesting support, e.g. ignore rra[0].cdp_prep[0].value
            // We also need inf/-inf support before allowing them
            if (false !== strpos($key, '[')) {
                return;
            }

            $array[$type][$idx][$key] = $value;
        }
    }

    /**
     * @return RrdInfo
     */
    public static function fromSerialization($any): RrdInfo
    {
        if (! is_object($any)) {
            throw new RuntimeException('Cannot un-serialize RrdInfo from ' . get_debug_type($any));
        }
        $self = new RrdInfo(
            $any->filename,
            $any->step,
            DsList::fromSerialization($any->ds),
            RraSet::fromSerialization($any->rra)
        );

        if (isset($any->rrdVersion)) {
            $self->rrdVersion = $any->rrdVersion;
        }
        if (isset($any->lastUpdate)) {
            $self->lastUpdate = $any->lastUpdate;
        }
        if (isset($any->headerSize)) {
            $self->headerSize = $any->headerSize;
        }

        return $self;
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'filename'   => $this->filename,
            'step'       => $this->step,
            'ds'         => $this->dsList,
            'rra'        => $this->rra,
            'rrdVersion' => $this->rrdVersion,
            'lastUpdate' => $this->lastUpdate,
            'headerSize' => $this->headerSize,
        ];
    }
}
