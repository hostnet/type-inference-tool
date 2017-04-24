<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

/**
 * Holds analyzed data for a function withing a class and namespace. This data
 * includes argument- and return types from calls.
 */
class AnalyzedFunction
{
    /**
     * @var AnalyzedClass
     */
    private $class;

    /**
     * @var string
     */
    private $function_name;

    /**
     * @var AnalyzedCall[]
     */
    private $collected_arguments = [];

    /**
     * @var AnalyzedReturn[]
     */
    private $collected_returns = [];

    /**
     * @param AnalyzedClass $class
     * @param string $function_name
     */
    public function __construct(AnalyzedClass $class, string $function_name)
    {
        $this->class         = $class;
        $this->function_name = $function_name;
    }

    /**
     * Appends an AnalyzedCall to a list with all analyzed calls.
     *
     * @param AnalyzedCall $call
     */
    public function addCollectedArguments(AnalyzedCall $call)
    {
        $this->collected_arguments[] = $call;
    }

    /**
     * Appends an array of AnalyzedCall to a list with all analyzed calls.
     *
     * @param AnalyzedCall[] $calls
     */
    public function addAllCollectedArguments(array $calls)
    {
        $this->collected_arguments = array_merge($this->collected_arguments, $calls);
    }

    /**
     * Appends an AnalyzedReturn to a list with all analyzed returns.
     *
     * @param AnalyzedReturn $return
     */
    public function addCollectedReturn(AnalyzedReturn $return)
    {
        $this->collected_returns[] = $return;
    }

    /**
     * Appends an array of AnalyzedReturn to a list with all analyzed returns.
     *
     * @param array $returns
     */
    public function addAllCollectedReturns(array $returns)
    {
        $this->collected_returns = array_merge($this->collected_returns, $returns);
    }

    /**
     * @return string
     */
    public function getFunctionName(): string
    {
        return $this->function_name;
    }

    /**
     * @return AnalyzedClass
     */
    public function getClass(): AnalyzedClass
    {
        return $this->class;
    }

    /**
     * @return AnalyzedCall[]
     */
    public function getCollectedArguments(): array
    {
        return $this->collected_arguments;
    }

    /**
     * @return AnalyzedReturn[]
     */
    public function getCollectedReturns(): array
    {
        return $this->collected_returns;
    }

    public function setClass(AnalyzedClass $class)
    {
        $this->class = $class;
    }
}
