<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

/**
 * Holds the arguments that were used as parameter on a function call.
 */
class AnalyzedCall
{
    /**
     * @var PhpType[] The first item in the array is the first argument, and so on
     */
    private $arguments;

    /**
     * @param PhpType[] $arguments
     */
    public function __construct(array $arguments)
    {
        $this->arguments = $arguments;
    }

    /**
     * @return PhpType[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
