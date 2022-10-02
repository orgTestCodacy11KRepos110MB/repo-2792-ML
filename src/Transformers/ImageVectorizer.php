<?php

namespace Rubix\ML\Transformers;

use Rubix\ML\DataType;
use Rubix\ML\Helpers\Params;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Exceptions\InvalidArgumentException;
use Rubix\ML\Specifications\ExtensionIsLoaded;
use Rubix\ML\Specifications\SamplesAreCompatibleWithTransformer;
use Rubix\ML\Exceptions\RuntimeException;

use function is_null;

/**
 * Image Vectorizer
 *
 * Image Vectorizer takes images of the same size and converts them into flat feature vectors
 * of raw color channel intensities. Intensities range from 0 to 255 and can either be read
 * from any of the color channels.
 *
 * > **Note**: The GD extension is required to use this transformer.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class ImageVectorizer implements Transformer, Stateful
{
    /**
     * Encode the red color channel?
     *
     * @var bool
     */
    protected bool $red;

    /**
     * Encode the green color channel?
     *
     * @var bool
     */
    protected bool $green;

    /**
     * Encode the blue color channel?
     *
     * @var bool
     */
    protected bool $blue;

    /**
     * The fixed width and height of the images for each image feature column.
     *
     * @var array<int[]>|null
     */
    protected ?array $sizes = null;

    /**
     * @param bool $red
     * @param bool $green
     * @param bool $blue
     * @throws \Rubix\ML\Exceptions\InvalidArgumentException
     */
    public function __construct(bool $red = true, bool $green = true, bool $blue = true)
    {
        ExtensionIsLoaded::with('gd')->check();

        if (!$red and !$green and !$blue) {
            throw new InvalidArgumentException('At least one color channel must be true.');
        }

        $this->red = $red;
        $this->green = $green;
        $this->blue = $blue;
    }

    /**
     * Return the data types that this transformer is compatible with.
     *
     * @internal
     *
     * @return list<\Rubix\ML\DataType>
     */
    public function compatibility() : array
    {
        return DataType::all();
    }

    /**
     * Is the transformer fitted?
     *
     * @return bool
     */
    public function fitted() : bool
    {
        return isset($this->sizes);
    }

    /**
     * Fit the transformer to a dataset.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @throws \Rubix\ML\Exceptions\InvalidArgumentException
     */
    public function fit(Dataset $dataset) : void
    {
        SamplesAreCompatibleWithTransformer::with($dataset, $this)->check();

        $sample = $dataset->sample(0);

        $this->sizes = [];

        foreach ($dataset->featureTypes() as $column => $type) {
            if ($type->isImage()) {
                $value = $sample[$column];

                $this->sizes[$column] = [
                    imagesx($value) ?: 0,
                    imagesy($value) ?: 0,
                ];
            }
        }
    }

    /**
     * Transform the dataset in place.
     *
     * @param list<list<mixed>> $samples
     * @throws \Rubix\ML\Exceptions\RuntimeException
     */
    public function transform(array &$samples) : void
    {
        if (is_null($this->sizes)) {
            throw new RuntimeException('Transformer has not been fitted.');
        }

        foreach ($samples as &$sample) {
            $vectors = [];

            foreach ($this->sizes as $column => [$width, $height]) {
                $value = $sample[$column];

                $vector = [];

                for ($x = 0; $x < $width; ++$x) {
                    for ($y = 0; $y < $height; ++$y) {
                        $pixel = imagecolorat($value, $x, $y);

                        if ($this->red) {
                            $vector[] = $pixel & 0xFF;
                        }

                        if ($this->green) {
                            $vector[] = ($pixel >> 8) & 0xFF;
                        }

                        if ($this->blue) {
                            $vector[] = ($pixel >> 16) & 0xFF;
                        }
                    }
                }

                unset($sample[$column]);

                imagedestroy($value);

                $vectors[] = $vector;
            }

            $sample = array_merge($sample, ...$vectors);
        }
    }

    /**
     * Return the string representation of the object.
     *
     * @internal
     *
     * @return string
     */
    public function __toString() : string
    {
        return 'Image Vectorizer (red: ' . Params::toString($this->red)
            . ', green: ' . Params::toString($this->green)
            . ', blue: ' . Params::toString($this->blue) . ')';
    }
}
