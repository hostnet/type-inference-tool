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
     * @var string|null
     */
    private $defined_return_type;

    /**
     * @var AnalyzedCall[]
     */
    private $collected_arguments = [];

    /**
     * @var AnalyzedReturn[]
     */
    private $collected_returns = [];

    /**
     * @var bool
     */
    private $has_return_declaration;

    /**
     * @param AnalyzedClass $class
     * @param string $function_name
     * @param string|null $return_type
     * @param bool $has_return_declaration
     */
    public function __construct(
        AnalyzedClass $class,
        string $function_name,
        string $return_type = null,
        bool $has_return_declaration = false
    ) {
        $this->class                  = $class;
        $this->function_name          = $function_name;
        $this->defined_return_type    = $return_type;
        $this->has_return_declaration = $has_return_declaration;
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

    /**
     * @return string|null
     */
    public function getDefinedReturnType()
    {
        return $this->defined_return_type;
    }

    /**
     * @param string $defined_return_type
     */
    public function setDefinedReturnType(string $defined_return_type)
    {
        $this->defined_return_type = $defined_return_type;
    }

    /**
     * Sets the class in which this function is declared.
     *
     * @param AnalyzedClass $class
     */
    public function setClass(AnalyzedClass $class)
    {
        $this->class = $class;
    }

    /**
     * @return bool
     */
    public function hasReturnDeclaration(): bool
    {
        return $this->has_return_declaration;
    }
}
