<?php

namespace IMEdge\RrdStructure;

use InvalidArgumentException;

use function count;
use function explode;
use function strlen;

class RraForecasting extends Rra
{
    /** @var string[] */
    protected static array $functions = [
        'HWPREDICT',
        'MHWPREDICT',
        'SEASONAL',
        'DEVSEASONAL',
        'DEVPREDICT',
        'FAILURES',
    ];

    /**
     * alpha is the adaption parameter of the intercept (or baseline) coefficient in the Holt-Winters forecasting
     * algorithm. See rrdtool for a description of this algorithm. alpha must lie between 0 and 1. A value closer
     * to 1 means that more recent observations carry greater weight in predicting the baseline component of the
     * forecast. A value closer to 0 means that past history carries greater weight in predicting the baseline
     * component.
     */
    protected ?float $alpha = null;

    /**
     * beta is the adaption parameter of the slope (or linear trend) coefficient in the Holt-Winters forecasting
     * algorithm. beta must lie between 0 and 1 and plays the same role as alpha with respect to the predicted
     * linear trend.
     */
    protected ?float $beta = null;

    /**
     * gamma is the adaption parameter of the seasonal coefficients in the Holt-Winters forecasting algorithm
     * (HWPREDICT) or the adaption parameter in the exponential smoothing update of the seasonal deviations. It
     * must lie between 0 and 1. If the SEASONAL and DEVSEASONAL RRAs are created implicitly, they will both
     * have the same value for gamma: the value specified for the HWPREDICT alpha argument.
     *
     * Note that because there is one seasonal coefficient (or deviation) for each time point during the seasonal
     * cycle, the adaptation rate is much slower than the baseline. Each seasonal coefficient is only updated (or
     * adapts) when the observed value occurs at the offset in the seasonal cycle corresponding to that coefficient.
     */
    protected ?float $gamma = null;

    /**
     * seasonal period specifies the number of primary data points in a seasonal cycle. If SEASONAL and DEVSEASONAL
     * are implicitly created, this argument for those RRAs is set automatically to the value specified by HWPREDICT.
     * If they are explicitly created, the creator should verify that all three seasonal period arguments agree.
     */
    protected ?int $seasonalPeriod = null;

    /**
     * rra-num provides the links between related RRAs. If HWPREDICT is specified alone and the other RRAs are created
     * implicitly, then there is no need to worry about this argument. If RRAs are created explicitly, then carefully
     * pay attention to this argument. For each RRA which includes this argument, there is a dependency between that
     * RRA and another RRA. The rra-num argument is the 1-based index in the order of RRA creation (that is, the order
     * they appear in the create command). The dependent RRA for each RRA requiring the rra-num argument is listed here:
     *
     * - HWPREDICT rra-num is the index of the SEASONAL RRA.
     * - SEASONAL rra-num is the index of the HWPREDICT RRA.
     * - DEVPREDICT rra-num is the index of the DEVSEASONAL RRA.
     * - DEVSEASONAL rra-num is the index of the HWPREDICT RRA.
     * - FAILURES rra-num is the index of the DEVSEASONAL RRA.
     */
    protected ?int $rraNum = null;

    /**
     * smoothing-window specifies the fraction of a season that should be averaged around each point. By default, the
     * value of smoothing-window is 0.05, which means each value in SEASONAL and DEVSEASONAL will be occasionally
     * replaced by averaging it with its (seasonal period*0.05) nearest neighbors. Setting smoothing-window to zero
     * will disable the running-average smoother altogether.
     */
    protected ?float $smoothingWindow = null;

    /**
     * @threshold is the minimum number of violations (observed values outside the confidence bounds) within a window
     * that constitutes a failure. If the FAILURES RRA is implicitly created, the default value is 7.
     */
    protected ?int $threshold = null;

    /**
     * window length is the number of time points in the window. Specify an integer greater than or equal to the
     * threshold and less than or equal to 28. The time interval this window represents depends on the interval between
     * primary data points. If the FAILURES RRA is implicitly created, the default value is 9.
     */
    protected ?int $windowLength = null;

    public static function isKnown(string $name): bool
    {
        return \in_array($name, self::$functions);
    }

    protected function parseNamedArgument(string $str): void
    {
        $pos = \strpos($str, '=');
        if ($pos === false) {
            throw new InvalidArgumentException(
                "Expected 'key=value', like 'smoothing-window=fraction' - got '$str''"
            );
        }

        $key = \substr($str, 0, $pos);
        $val = \substr($str, $pos + 1);
        switch ($key) {
            case 'smoothing-window':
                $this->smoothingWindow = (float) $val;
                break;
            default:
                throw new InvalidArgumentException(
                    "Got unknown named argument '$key'"
                );
        }
    }

    public function toString(): string
    {
        switch ($this->consolidationFunction) {
            case 'HWPREDICT':
            case 'MHWPREDICT':
                $result = \implode(':', [
                    $this->rows,
                    $this->alpha,
                    $this->beta,
                    $this->seasonalPeriod
                ]);
                if ($this->rraNum !== null) {
                    $result .= ':' . $this->rraNum;
                }
                break;
            case 'SEASONAL':
            case 'DEVSEASONAL':
                $result = \implode(':', [
                    $this->seasonalPeriod,
                    $this->gamma,
                    $this->rraNum
                ]);
                if ($this->smoothingWindow !== null) {
                    $result .= ':smoothing-window=' . $this->smoothingWindow;
                }
                break;
            case 'DEVPREDICT':
                $result = \implode(':', [
                    $this->rows,
                    $this->rraNum
                ]);
                break;
            case 'FAILURES':
                $result = \implode(':', [
                    $this->rows,
                    $this->threshold,
                    $this->windowLength,
                    $this->rraNum
                ]);
                break;
            default:
                throw new \RuntimeException("Not a valid forecasting function: " . $this->consolidationFunction);
        }

        return $result;
    }

    /**
     * TODO: Check whether this really applies to forecasting RRAs
     */
    public function getDataSize(): int
    {
        return $this->rows * static::BYTES_PER_DATAPOINT;
    }

    /**
     * xff:steps:rows
     */
    public function setArgumentsFromString(string $str): void
    {
        $parts = explode(':', $str);
        $cntParts = count($parts);


        switch ($this->consolidationFunction) {
            case 'HWPREDICT':
            case 'MHWPREDICT':
                if ($cntParts < 4 || $cntParts > 5) {
                    throw new InvalidArgumentException(
                        "Expected 'rows:alpha:beta:seasonal period[:rra-num]', got '$str'"
                    );
                }
                $this->rows = (int) $parts[0];
                $this->alpha = (float) $parts[1];
                $this->beta = (float) $parts[2];
                $this->seasonalPeriod = (int) $parts[3];
                if (isset($parts[4]) && strlen($parts[4])) {
                    $this->rraNum = (int) $parts[4];
                }
                break;
            case 'SEASONAL':
            case 'DEVSEASONAL':
                if ($cntParts < 3 || $cntParts > 4) {
                    throw new InvalidArgumentException(
                        "Expected 'seasonal period:gamma:rra-num[:smoothing-window=fraction]', got '$str'"
                    );
                }
                $this->seasonalPeriod = (int) $parts[0];
                $this->gamma = (float) $parts[1];
                $this->rraNum = (int) $parts[2];
                if (isset($parts[3]) && strlen($parts[3])) {
                    $this->parseNamedArgument($parts[3]);
                }
                break;
            case 'DEVPREDICT':
                if ($cntParts !== 2) {
                    throw new InvalidArgumentException(
                        "Expected 'rows:rra-num', got '$str'"
                    );
                }
                $this->rows = (int) $parts[0];
                $this->rraNum = (int) $parts[1];
                break;
            case 'FAILURES':
                if ($cntParts !== 4) {
                    throw new InvalidArgumentException(
                        "Expected 'rows:threshold:window length:rra-num', got '$str'"
                    );
                }

                $this->rows = (int) $parts[0];
                $this->threshold = (int) $parts[1];
                $this->windowLength = (int) $parts[2];
                $this->rraNum = (int) $parts[3];
                break;
        }
    }
}
