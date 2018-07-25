<?php

namespace Rubix\ML\AnomalyDetectors;

use Rubix\ML\Ensemble;
use Rubix\ML\Persistable;
use Rubix\ML\Probabilistic;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Datasets\Labeled;
use MathPHP\Statistics\Average;
use InvalidArgumentException;

/**
 * Isolation Forest
 *
 * An Ensemble Anomaly Detector comprised of Isolation Trees each trained on a
 * different subset of the training set. The Isolation Forest works by averaging
 * the isolation score of a sample across a user-specified number of trees.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class IsolationForest implements Detector, Ensemble, Probabilistic, Persistable
{
    /**
     * The number of trees to train in the ensemble.
     *
     * @var int
     */
    protected $trees;

    /**
     * The ratio of training samples to train each isolation tree on.
     *
     * @var float
     */
    protected $ratio;

    /**
     * The threshold isolation score. Score is a value between 0 and 1 where
     * greater than 0.5 signifies outlier territory.
     *
     * @var float
     */
    protected $threshold;

    /**
     * The isolation trees that make up the forest.
     *
     * @var array
     */
    protected $forest = [
        //
    ];

    /**
     * @param  int  $trees
     * @param  float  $ratio
     * @param  float  $threshold
     * @throws \InvalidArgumentException
     * @return void
     */
    public function __construct(int $trees = 300, float $ratio = 0.1, float $threshold = 0.5)
    {
        if ($trees < 1) {
            throw new InvalidArgumentException('The number of trees cannot be'
                . ' less than 1.');
        }

        if ($ratio < 0.01 or $ratio > 1.0) {
            throw new InvalidArgumentException('Sample ratio must be a float'
                . ' value between 0.01 and 1.0.');
        }

        if ($threshold < 0 or $threshold > 1) {
            throw new InvalidArgumentException('Threshold isolation score must'
                . ' be between 0 and 1.');
        }

        $this->trees = $trees;
        $this->ratio = $ratio;
        $this->threshold = $threshold;
    }

    /**
     * Return the ensemble of estimators.
     *
     * @return array
     */
    public function estimators() : array
    {
        return $this->forest;
    }

    /**
     * Train a Random Forest by training an ensemble of decision trees on random
     * subsets of the training data.
     *
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @throws \InvalidArgumentException
     * @return void
     */
    public function train(Dataset $dataset) : void
    {
        $n = (int) round($this->ratio * $dataset->numRows());

        $maxDepth = (int) ceil(log($n, 2));

        $this->forest = [];

        for ($i = 0; $i < $this->trees; $i++) {
            $tree = new IsolationTree($maxDepth, 1, $this->threshold);

            $tree->train($dataset->randomSubset($n));

            $this->forest[] = $tree;
        }
    }

    /**
     * Output a vector of class probabilities per sample.
     *
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @return array
     */
    public function predict(Dataset $dataset) : array
    {
        $predictions = [];

        foreach ($this->proba($dataset) as $probability) {
            $predictions[] = $probability > $this->threshold ? 1 : 0;
        }

        return $predictions;
    }

    /**
     * Output a vector of class probabilities per sample.
     *
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @return array
     */
    public function proba(Dataset $dataset) : array
    {
        $n = count($this->forest) + self::EPSILON;

        $probabilities = [];

        foreach ($dataset as $sample) {
            $probability = 0.0;

            foreach ($this->forest as $tree) {
                $probability += $tree->search($sample)->score();
            }

            $probabilities[] = $probability / $n;
        }

        return $probabilities;
    }
}