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
    /*
     * TODO - Replace $namespace and $class_name for object AnalyzedClass
     */

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $class_name;

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
     * @param string $namespace
     * @param string $class_name
     * @param string $function_name
     */
    public function __construct(string $namespace, string $class_name, string $function_name)
    {
        $this->namespace     = $namespace;
        $this->class_name    = $class_name;
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
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->class_name;
    }

    /**
     * @return string
     */
    public function getFunctionName(): string
    {
        return $this->function_name;
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

    public function getFqcn(): string
    {
        return $this->namespace . '\\' . $this->class_name;
    }
}
