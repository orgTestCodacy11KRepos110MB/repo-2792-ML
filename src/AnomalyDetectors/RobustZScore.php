<?php

namespace Rubix\ML\AnomalyDetectors;

use Rubix\ML\Persistable;
use Rubix\ML\Datasets\Dataset;
use MathPHP\Statistics\Average;
use InvalidArgumentException;

/**
 * Robust Z Score
 *
 * A quick global anomaly Detector, Robust Z Score uses a threshold to detect
 * outliers within a Dataset. The modified Z score consists of taking the median
 * and median absolute deviation (MAD) instead of the mean and standard
 * deviation thus making the statistic more robust to training sets that may
 * already contain outliers.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class RobustZScore implements Detector, Persistable
{
    const LAMBDA = 0.6745;

    /**
     * The average z score to tolerate before a sample is considered an outlier.
     *
     * @var float
     */
    protected $tolerance;

    /**
     * The threshold z score of a individual feature to consider the entire
     * sample an outlier.
     *
     * @var float
     */
    protected $threshold;

    /**
     * The median of each training feature column.
     *
     * @var array
     */
    protected $medians = [
        //
    ];

    /**
     * The median absolute deviation of each training feature column.
     *
     * @var array
     */
    protected $mads = [
        //
    ];

    /**
     * @param  float  $tolerance
     * @param  float  $threshold
     * @throws \InvalidArgumentException
     * @return void
     */
    public function __construct(float $tolerance = 3.0, float $threshold = 3.5)
    {
        if ($tolerance < 0) {
            throw new InvalidArgumentException('Z score tolerance must be'
                . ' 0 or greater.');
        }

        if ($threshold < 0) {
            throw new InvalidArgumentException('Z score threshold must be'
                . ' 0 or greater.');
        }

        $this->tolerance = $tolerance;
        $this->threshold = $threshold;
    }

    /**
     * Return the array of computed feature column medians.
     *
     * @return array
     */
    public function medians() : array
    {
        return $this->medians;
    }

    /**
     * Return the array of computed feature column median absolute deviations.
     *
     * @return array
     */
    public function mads() : array
    {
        return $this->mads;
    }

    /**
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @throws \InvalidArgumentException
     * @return void
     */
    public function train(Dataset $dataset) : void
    {
        $this->medians = $this->mads = [];

        if (in_array(self::CATEGORICAL, $dataset->columnTypes())) {
            throw new InvalidArgumentException('This estimator only works with'
                . ' continuous features.');
        }

        foreach ($dataset->rotate() as $column => $values) {
            $median = Average::median($values);

            $deviations = [];

            foreach ($values as $value) {
                $deviations[] = abs($value - $median);
            }

            $this->mads[$column] = Average::median($deviations);

            $this->medians[$column] = $median;
        }
    }

    /**
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @return array
     */
    public function predict(Dataset $dataset) : array
    {
        $predictions = [];

        foreach ($dataset as $sample) {
            $score = 0.0;

            foreach ($sample as $column => $feature) {
                $z = (self::LAMBDA * ($feature - $this->medians[$column]))
                    / $this->mads[$column];

                if ($z > $this->threshold) {
                    $predictions[] = 1;

                    continue 2;
                }

                $score += $z;
            }

            $score /= count($sample);

            $predictions[] = $score > $this->tolerance ? 1 : 0;
        }

        return $predictions;
    }
}