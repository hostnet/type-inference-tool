<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\Data\Exception\EntryNotFoundException;

/**
 * Holds a traversable collection of {@link AnalyzedFunction}.
 */
class AnalyzedFunctionCollection implements \Iterator
{
    /**
     * @var AnalyzedFunction[]
     */
    private $analyzed_functions = [];
    private $position           = 0;

    /**
     * Appends a list of AnalyzedFunction to the collection. If the function itself
     * already exists in the collection, the AnalyzedCalls and AnalyzedReturns will be
     * appended.
     *
     * @param AnalyzedFunction[] $analyzed_functions
     */
    public function addAll(array $analyzed_functions)
    {
        foreach ($analyzed_functions as $function) {
            try {
                $existing_analyzed_function = $this->get(
                    $function->getClass()->getFqcn(),
                    $function->getFunctionName()
                );

                $existing_analyzed_function->addAllCollectedArguments($function->getCollectedArguments());
                $existing_analyzed_function->addAllCollectedReturns($function->getCollectedReturns());
            } catch (EntryNotFoundException $e) {
                $this->analyzed_functions[] = $function;
            }
        }
    }

    /**
     * Returns the AnalyzedFunction in the collection.
     *
     * @param string $fqcn
     * @param string $function_name
     * @return AnalyzedFunction
     * @throws EntryNotFoundException
     */
    private function get(string $fqcn, string $function_name): AnalyzedFunction
    {
        foreach ($this->analyzed_functions as $function) {
            if ($function->getFunctionName() === $function_name && $function->getClass()->getFqcn() === $fqcn) {
                return $function;
            }
        }

        throw new EntryNotFoundException(sprintf(
            'AnalyzedFunction %s::%s does not exist',
            $fqcn,
            $function_name
        ));
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        return $this->analyzed_functions[$this->position];
    }

    /**
     * @inheritdoc
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * @inheritdoc
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        return isset($this->analyzed_functions[$this->position]);
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        $this->position = 0;
    }
}
