<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\Data\Type\PhpTypeInterface;

/**
 * Holds a return type from an analyzed function.
 */
class AnalyzedReturn
{
    /**
     * @var PhpTypeInterface
     */
    private $type;

    /**
     * @param PhpTypeInterface $type
     */
    public function __construct(PhpTypeInterface $type)
    {
        $this->type = $type;
    }

    /**
     * Removes duplicates in an array of AnalyzedReturns.
     *
     * @param AnalyzedReturn[] $analyzed_returns
     * @return AnalyzedReturn[]
     */
    public static function removeAnalyzedReturnsDuplicates(array $analyzed_returns): array
    {
        $unique_returns = [];

        foreach ($analyzed_returns as $analyzed_return) {
            if (array_key_exists($analyzed_return->getType()->getName(), $unique_returns)) {
                continue;
            }

            $unique_returns[$analyzed_return->getType()->getName()] = $analyzed_return;
        }

        return array_values($unique_returns);
    }

    /**
     * @return PhpTypeInterface
     */
    public function getType(): PhpTypeInterface
    {
        return $this->type;
    }
}
