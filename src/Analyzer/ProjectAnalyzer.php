<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedReturn;
use Hostnet\Component\TypeInference\Analyzer\Data\PhpType;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\AbstractInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\ReturnTypeInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\TypeHintInstruction;
use Psr\Log\LoggerInterface;

/**
 * Uses analyzers to retrieve parameter- and return types. Determines
 * whether type hints and return type declarations can be added, if
 * so, creates instructions to do so.
 */
class ProjectAnalyzer
{
    /**
     * @var string
     */
    private $target_project;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FunctionAnalyzerInterface[]
     */
    private $analyzers = [];

    /**
     * @param string $target_project
     * @param LoggerInterface $logger
     */
    public function __construct(string $target_project, LoggerInterface $logger)
    {
        $this->target_project = $target_project;
        $this->logger         = $logger;
    }

    /**
     * Uses analyzers to collect AnalyzedFunctions. Determines whether type hints and return type
     * declarations can be added. In case type hints or return type declarations can be added,
     * instructions are created to do so.
     *
     * @return AbstractInstruction[] Instructions containing type hints and return types to be added
     */
    public function analyse(): array
    {
        $analyzed_functions_collection = $this->collectAnalyzedFunctions();
        return $this->determineTypes($analyzed_functions_collection);
    }

    /**
     * Collects AnalyzedFunctions by applying static- and dynamic analysis.
     *
     * @return AnalyzedFunctionCollection
     */
    private function collectAnalyzedFunctions(): AnalyzedFunctionCollection
    {
        $analyzed_functions_collection = new AnalyzedFunctionCollection();

        foreach ($this->analyzers as $analyzer) {
            $analyzed_functions_collection->addAll($analyzer->collectAnalyzedFunctions($this->target_project));
        }

        return $analyzed_functions_collection;
    }

    /**
     * Determines for each analyzed function whether a return type declaration or
     * type hint can be added. If so, a CodeEditorInstruction is created.
     *
     * @param AnalyzedFunctionCollection $analyzed_functions_collection
     * @return AbstractInstruction[]
     */
    private function determineTypes(AnalyzedFunctionCollection $analyzed_functions_collection): array
    {
        $instructions = [];

        foreach ($analyzed_functions_collection as $analyzed_function) {
            $return_type = $this->determineReturnType($analyzed_function);
            if (!in_array($return_type->getName(), [PhpType::NONE, PhpType::INCONSISTENT], true)) {
                $instructions[] = new ReturnTypeInstruction(
                    $analyzed_function->getNamespace(),
                    $analyzed_function->getClassName(),
                    $analyzed_function->getFunctionName(),
                    $return_type
                );
            }

            $parameter_types = $this->determineParameterTypes($analyzed_function);
            foreach ($parameter_types as $arg_number => $parameter_type) {
                if (in_array($parameter_type->getName(), [PhpType::NONE, PhpType::INCONSISTENT], true)) {
                    continue;
                }

                $instructions[] = new TypeHintInstruction(
                    $analyzed_function->getNamespace(),
                    $analyzed_function->getClassName(),
                    $analyzed_function->getFunctionName(),
                    $arg_number,
                    $parameter_type
                );
            }
        }

        return $instructions;
    }

    /**
     * Taken an AnalyzedFunction and determines whether the return type is always
     * consistent. If so, that type gets returned.
     *
     * @param AnalyzedFunction $analyzed_function
     * @return PhpType
     */
    private function determineReturnType(AnalyzedFunction $analyzed_function): PhpType
    {
        $return_types        = $this->removeAnalyzedReturnsDuplicates($analyzed_function->getCollectedReturns());
        $amount_return_types = count($return_types);

        if ($amount_return_types === 1) {
            return $return_types[0]->getType();
        }
        if ($amount_return_types > 1) {
            // TODO - Determine whether objects have common parent (using AnalyzedClass object)
            $used_types_names = [];
            foreach ($return_types as $type) {
                $used_types_names[] = $type->getType()->getName();
            }

            $this->logger->warning(
                "Inconsistent return types for function '{fqcn}::{function}': {types}.",
                [
                    'fqcn' => $analyzed_function->getFqcn(),
                    'function' => $analyzed_function->getFunctionName(),
                    'types' => implode(', ', $used_types_names)
                ]
            );
            return new PhpType(PhpType::INCONSISTENT);
        }

        return new PhpType(PhpType::NONE);
    }

    /**
     * @param AnalyzedReturn[] $analyzed_returns
     * @return AnalyzedReturn[]
     */
    private function removeAnalyzedReturnsDuplicates(array $analyzed_returns): array
    {
        $unique_returns = [];

        foreach ($analyzed_returns as $analyzed_return) {
            if (!array_key_exists($analyzed_return->getType()->getName(), $unique_returns)) {
                $unique_returns[$analyzed_return->getType()->getName()] = $analyzed_return;
            }
        }

        return array_values($unique_returns);
    }

    /**
     * Takes an AnalyzedFunction and determines for each argument whether a type hint
     * could be added.
     *
     * @param AnalyzedFunction $analyzed_function
     * @return PhpType[] The first items is the type of the first argument and so on
     */
    private function determineParameterTypes(AnalyzedFunction $analyzed_function): array
    {
        $used_types_per_argument = [];
        $analyzed_calls          = $analyzed_function->getCollectedArguments();
        foreach ($analyzed_calls as $analyzed_call) {
            foreach ($analyzed_call->getArguments() as $i => $php_type) {
                $used_types_per_argument[$i][] = $php_type;
            }
        }

        $type_per_argument = [];
        foreach ($used_types_per_argument as $arg_number => $php_types) {
            $used_types   = $this->removePhpTypeDuplicates($php_types);
            $amount_types = count($used_types);
            if ($amount_types === 1) {
                $type_per_argument[$arg_number] = $used_types[0];
                continue;
            }
            if ($amount_types > 1) {
                // TODO - Determine whether objects have a common parent (using AnalyzedClass object)
                $used_types_names = [];
                foreach ($used_types as $type) {
                    $used_types_names[] = $type->getName();
                }

                $this->logger->warning(
                    "Inconsistent types used for argument {arg_nr} in function '{fqcn}::{function}': {types}.",
                    [
                        'arg_nr' => $arg_number,
                        'function' => $analyzed_function->getFunctionName(),
                        'fqcn' => $analyzed_function->getFqcn(),
                        'types' => implode(', ', $used_types_names)
                    ]
                );
                $type_per_argument[$arg_number] = new PhpType(PhpType::INCONSISTENT);
            }
        }
        return $type_per_argument;
    }

    /**
     * @param PhpType[] $php_types
     * @return PhpType[]
     */
    private function removePhpTypeDuplicates(array $php_types): array
    {
        $unique_types = [];

        foreach ($php_types as $php_type) {
            if (!array_key_exists($php_type->getName(), $unique_types)) {
                $unique_types[$php_type->getName()] = $php_type;
            }
        }

        return array_values($unique_types);
    }

    /**
     * Adds an analyzer that is used during analysis.
     *
     * @param FunctionAnalyzerInterface $analyzer
     */
    public function addAnalyzer(FunctionAnalyzerInterface $analyzer)
    {
        $this->analyzers[] = $analyzer;
    }
}
