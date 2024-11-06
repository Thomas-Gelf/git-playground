<?php

namespace IMEdge\RrdStructure;

class DsInfo
{
    public string $name;
    public int $index;
    public string $type;
    public int $minimalHeartbeat;
    public ?int $min = null;
    public ?int $max = null;

    /**
     * https://stackoverflow.com/questions/41373910/what-is-rrd-last-ds
     *
     * The last received value of this DS, prior to calculation of Rate, at last_update time
     * When a new update comes in with a new DS value, this is used to create the new value
     * for the update interval...
     *
     * new_value = ( new_ds - last_ds ) / ( current_time - last_update )
     *
     * ...and this is then assigned to one (or more) Intervals (according to Data Normalisation)
     * in order to be able to set values in the various RRAs.
     *
     * last_ds is different from value as it is before rate calculations and normalisation.
     */
    public ?string $lastDs = null;

    /** @var int|float|null or more? */
    public $value;

    public ?int $unknownSec = null;

    protected function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param array{
     *     index: int,
     *     value: int|float|null,
     *     type: string,
     *     minimal_heartbeat: int,
     *     min: ?int,
     *     max: ?int,
     *     last_ds: ?string,
     *     unknown_sec: ?int
     *  } $values
     */
    public static function fromArray(string $name, array $values): DsInfo
    {
        $info = new DsInfo($name);
        foreach ($values as $arrayKey => $value) {
            switch ($arrayKey) {
                case 'index':
                case 'type':
                case 'min':
                case 'max':
                case 'value':
                    $info->$arrayKey = $value;
                    break;
                case 'minimal_heartbeat':
                    $info->minimalHeartbeat = $value;
                    break;
                case 'last_ds':
                    $info->lastDs = (string) $value; // Why string?
                    break;
                case 'unknown_sec':
                    $info->unknownSec = $value;
                    break;
                default:
                    // Ignore unknown properties
                    // TODO: set whatever we need
            }
        }

        return $info;
    }

    public function toDs(): Ds
    {
        return new Ds(
            $this->name,
            $this->type,
            $this->minimalHeartbeat,
            $this->min,
            $this->max
        );
    }
}
