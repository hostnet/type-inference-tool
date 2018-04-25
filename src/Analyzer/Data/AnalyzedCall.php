<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\Data\Type\PhpTypeInterface;

/**
 * Holds the arguments that were used as parameter on a function call.
 */
class AnalyzedCall
{
    /**
     * @var PhpTypeInterface[] The first item in the array is the first argument, and so on
     */
    private $arguments;

    /**
     * @param PhpTypeInterface[] $arguments
     */
    public function __construct(array $arguments)
    {
        $this->arguments = $arguments;
    }

    /**
     * @return PhpTypeInterface[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
