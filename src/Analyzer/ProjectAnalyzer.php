<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedReturn;
use Hostnet\Component\TypeInference\Analyzer\Data\Exception\EntryNotFoundException;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\PhpTypeInterface;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\UnresolvablePhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\UnresolvableHint;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\AbstractInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\ReturnTypeInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\TypeHintInstruction;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Uses analyzers to retrieve parameter- and return types. Determines whether type
 * hints and return type declarations can be added, if so, creates instructions to
 * do so.
 */
class ProjectAnalyzer
{
    /**
     * Prefix used for logs outputted by this class. Also used
     * by stopwatch for this class.
     */
    const TIMER_LOG_NAME = 'PROJECT_ANALYZER';
    const VENDOR_FOLDER  = 'vendor';

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
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Uses analyzers to collect AnalyzedFunctions. Determines whether type
     * hints and return type declarations can be added. In case type hints
     * or return type declarations can be added, instructions are created to
     * do so.
     *
     * @param string $target_project
     * @return AbstractInstruction[]
     * @throws \InvalidArgumentException
     */
    public function analyse(string $target_project): array
    {
        $this->target_project          = $target_project;
        $analyzed_functions_collection = $this->collectAnalyzedFunctions();
        $instructions                  = $this->generateInstructions($analyzed_functions_collection);
        return $this->handleCovariance($analyzed_functions_collection, $instructions);
    }

    /**
     * Collects AnalyzedFunctions by applying analyzers.
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
     * type hint should be added. If so, a CodeEditorInstruction is created.
     *
     * @param AnalyzedFunctionCollection $analyzed_functions_collection
     * @return AbstractInstruction[]
     * @throws \InvalidArgumentException
     */
    private function generateInstructions(AnalyzedFunctionCollection $analyzed_functions_collection): array
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start(self::TIMER_LOG_NAME);
        $this->logger->info(self::TIMER_LOG_NAME . ': Started determining types');
        $instructions = [];

        foreach ($analyzed_functions_collection as $analyzed_function) {
            $overridden_classes = $analyzed_functions_collection->getFunctionParents($analyzed_function);
            if ($this->containsVendorClass($overridden_classes)) {
                $this->logImmutableParent($analyzed_function, $overridden_classes);
                continue;
            }

            $instructions = array_merge(
                $instructions,
                $this->generateTypeHintInstructions($analyzed_function, $overridden_classes)
            );
            $instructions = array_merge(
                $instructions,
                $this->generateReturnTypeInstruction($analyzed_function, $overridden_classes)
            );
        }

        $this->logger->info(self::TIMER_LOG_NAME . ': Finished determining types ({time}s)', [
            'time' => round($stopwatch->stop(self::TIMER_LOG_NAME)->getDuration() / 1000, 2)
        ]);

        return $instructions;
    }

    /**
     * Checks whether an AnalyzedFunction should have type hints. If that's
     * the case, an instruction is generated.
     *
     * @param AnalyzedFunction $analyzed_function
     * @param AnalyzedClass[] $overridden_classes
     * @return TypeHintInstruction[]
     * @throws \InvalidArgumentException
     */
    private function generateTypeHintInstructions(
        AnalyzedFunction $analyzed_function,
        array $overridden_classes = []
    ): array {
        $instructions    = [];
        $parameter_types = $this->determineParameterTypes($analyzed_function);

        foreach ($parameter_types as $arg_number => $param_type) {
            if ($param_type instanceof UnresolvablePhpType) {
                continue;
            }

            foreach ($overridden_classes as $overridden_class) {
                $instructions[] = new TypeHintInstruction(
                    $overridden_class,
                    $analyzed_function->getFunctionName(),
                    $arg_number,
                    $param_type,
                    $this->logger
                );
            }

            $instructions[] = new TypeHintInstruction(
                $analyzed_function->getClass(),
                $analyzed_function->getFunctionName(),
                $arg_number,
                $param_type,
                $this->logger
            );
        }

        return $instructions;
    }

    /**
     * Checks whether an AnalyzedFunction should have a return type declaration. If
     * that's the case, an instruction is generated.
     *
     * @param AnalyzedFunction $analyzed_function
     * @param AnalyzedClass[] $overridden_classes
     * @return ReturnTypeInstruction[]
     * @throws \InvalidArgumentException
     */
    private function generateReturnTypeInstruction(
        AnalyzedFunction $analyzed_function,
        array $overridden_classes = []
    ): array {
        $instructions = [];
        $return_type  = $this->determineReturnType($analyzed_function);

        if ($return_type instanceof UnresolvablePhpType) {
            return [];
        }

        $instructions[] = new ReturnTypeInstruction(
            $analyzed_function->getClass(),
            $analyzed_function->getFunctionName(),
            $return_type,
            $this->logger
        );

        foreach ($overridden_classes as $overridden_class) {
            $instructions[] = new ReturnTypeInstruction(
                $overridden_class,
                $analyzed_function->getFunctionName(),
                $return_type,
                $this->logger
            );
        }

        return $instructions;
    }

    /**
     * Taken an AnalyzedFunction and determines whether the return type is always
     * consistent. If so, that type gets returned.
     *
     * @param AnalyzedFunction $analyzed_function
     * @return PhpTypeInterface
     * @throws \InvalidArgumentException
     */
    private function determineReturnType(AnalyzedFunction $analyzed_function): PhpTypeInterface
    {
        $return_types = AnalyzedReturn::removeAnalyzedReturnsDuplicates($analyzed_function->getCollectedReturns());

        $amount_return_types = count($return_types);
        if ($amount_return_types === 1) {
            return $return_types[0]->getType();
        }

        if ($amount_return_types > 1) {
            $return_type = $this->resolveMultipleTypes(array_map(function (AnalyzedReturn $type) {
                return $type->getType();
            }, $return_types));

            if ($return_type instanceof UnresolvablePhpType) {
                $this->logInconsistentReturnType($analyzed_function, $return_types);
            }

            return $return_type;
        }

        return new UnresolvablePhpType(UnresolvablePhpType::NONE);
    }

    /**
     * Takes an AnalyzedFunction and determines for each argument whether a type hint
     * could be added.
     *
     * @param AnalyzedFunction $analyzed_function
     * @return PhpTypeInterface[] The first items is the type of the first argument and so on
     * @throws \InvalidArgumentException
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
            $used_types   = $this->filterPhpTypes($php_types);
            $amount_types = count($used_types);

            if ($amount_types === 1) {
                $type_per_argument[$arg_number] = $used_types[0];
                continue;
            }

            if ($amount_types > 1) {
                $parent = $this->resolveMultipleTypes($used_types);
                if ($parent instanceof UnresolvablePhpType) {
                    $this->logInconsistentParamType($analyzed_function, $used_types, $arg_number);
                }
                $type_per_argument[$arg_number] = $parent;
            }
        }

        return $type_per_argument;
    }

    /**
     * TODO - Handle nullable parameter types
     *
     * Takes an array of PhpTypeInterface and determines whether these could be
     * resolved to one type. For example, when multiple objects have the same
     * parent, that parent is the resolved type. Also, mixed usage between float
     * and int resolves to float.
     *
     * @param PhpTypeInterface[] $types
     * @return PhpTypeInterface
     * @throws \InvalidArgumentException
     */
    private function resolveMultipleTypes(array $types): PhpTypeInterface
    {
        if (!$this->containsOnlyScalars($types)) {
            return NonScalarPhpType::getCommonParent($types);
        }

        $scalar_types = array_map(function (PhpTypeInterface $type) {
            return $type->getName();
        }, $types);

        if (count(array_diff($scalar_types, [ScalarPhpType::TYPE_FLOAT, ScalarPhpType::TYPE_INT])) === 0) {
            return new ScalarPhpType(ScalarPhpType::TYPE_FLOAT);
        }

        return new UnresolvablePhpType(UnresolvablePhpType::INCONSISTENT);
    }

    /**
     * Handles return type and parameter type hints covariance. Due to sub typing 'rules'
     * subclasses or their parents can't always be type hinted. This function is used to
     * remove instructions that would violate these rules.
     *
     * @param AnalyzedFunctionCollection $analyzed_function_collection
     * @param AbstractInstruction[] $instructions
     * @return AbstractInstruction[]
     */
    private function handleCovariance(
        AnalyzedFunctionCollection $analyzed_function_collection,
        array $instructions
    ): array {
        $analyzed_function_collection->applyInstructions($instructions);
        $instructions = $this->handleReturnTypeCovariance($analyzed_function_collection, $instructions);
        return $this->handleParamTypeHintCovariance($analyzed_function_collection, $instructions);
    }

    /**
     * Used to remove TypeHintInstructions that would violate sub typing limits. All children
     * must have the same parameter type hints as their parent. If the generated instructions
     * would violate this rule, the instruction would be removed.
     *
     * PHP 7.2 will handle this differently (https://wiki.php.net/rfc/parameter-no-type-variance),
     * as for now the main focus is on PHP 7.0.
     *
     * @param AnalyzedFunctionCollection $analyzed_function_collection
     * @param AbstractInstruction[] $instructions
     * @return AbstractInstruction[]
     */
    private function handleParamTypeHintCovariance(
        AnalyzedFunctionCollection $analyzed_function_collection,
        array $instructions
    ): array {
        $unresolvable_hints = [];

        foreach ($analyzed_function_collection as $analyzed_function) {
            $analyzed_function_parents = $analyzed_function_collection->getFunctionParents($analyzed_function);

            foreach ($analyzed_function_parents as $function_parent_class) {
                foreach ($analyzed_function->getDefinedParameters() as $i => $function_parameter) {
                    try {
                        $parent_function = $analyzed_function_collection->get(
                            $function_parent_class->getFqcn(),
                            $analyzed_function->getFunctionName()
                        );
                    } catch (EntryNotFoundException $e) {
                        continue;
                    }

                    if ($function_parameter->getType() ===
                        $parent_function->getDefinedParameters()[$i]->getType()
                    ) {
                        continue;
                    }

                    $unresolvable_hints[] = new UnresolvableHint(
                        $function_parent_class,
                        $analyzed_function->getFunctionName(),
                        UnresolvableHint::HINT_TYPE_PARAMETER,
                        $i
                    );

                    $children = $analyzed_function_collection->getFunctionChildren(
                        $function_parent_class,
                        $analyzed_function->getFunctionName()
                    );

                    foreach ($children as $unresolvable_child) {
                        $unresolvable_hints[] = new UnresolvableHint(
                            $unresolvable_child,
                            $analyzed_function->getFunctionName(),
                            UnresolvableHint::HINT_TYPE_PARAMETER,
                            $i
                        );
                    }

                    $this->logUnresolvableParentTypeHint(
                        $function_parent_class->getFqcn(),
                        $analyzed_function->getFunctionName(),
                        $i
                    );
                }
            }
        }

        return $this->removeInstructions($instructions, $unresolvable_hints);
    }

    /**
     * Used to remove return type instructions that would violate some of the sub typing
     * limits. When parent functions already have return type declarations, its children
     * must be compatible with those return types. When a parent does not have a return
     * type declaration, its children can have different return types. In that case siblings
     * may also differ in their return types.
     *
     * @param AnalyzedFunctionCollection $analyzed_function_collection
     * @param AbstractInstruction[] $instructions
     * @return AbstractInstruction[]
     */
    private function handleReturnTypeCovariance(
        AnalyzedFunctionCollection $analyzed_function_collection,
        array $instructions
    ): array {
        $unresolvable_hints = [];

        foreach ($analyzed_function_collection as $analyzed_function) {
            $parent_definition_classes = $analyzed_function_collection->getFunctionParents($analyzed_function);

            foreach ($parent_definition_classes as $parent_definition_class) {
                $function_name = $analyzed_function->getFunctionName();

                try {
                    $analyzed_parent_function = $analyzed_function_collection->get(
                        $parent_definition_class->getFqcn(),
                        $function_name
                    );
                } catch (EntryNotFoundException $e) {
                    continue;
                }

                $child_return  = $analyzed_function->getDefinedReturnType();
                $parent_return = $analyzed_parent_function->getDefinedReturnType();

                if ($parent_return === $child_return || ($parent_return === null && $child_return !== null)) {
                    continue;
                }

                foreach ($parent_definition_classes as $unresolvable_parent_function) {
                    $unresolvable_returns[$unresolvable_parent_function->getFqcn()][] = $function_name;

                    $unresolvable_hints[] = new UnresolvableHint(
                        $unresolvable_parent_function,
                        $function_name,
                        UnresolvableHint::HINT_TYPE_RETURN
                    );

                    $this->logUnresolvableParentReturn(
                        $function_name,
                        $unresolvable_parent_function->getFqcn(),
                        $parent_return,
                        $analyzed_function->getClass()->getFqcn(),
                        $child_return
                    );
                }

                if ($parent_return === $child_return || !$analyzed_parent_function->hasReturnDeclaration()) {
                    continue;
                }

                $unresolvable_hints[] = new UnresolvableHint(
                    $analyzed_function->getClass(),
                    $function_name,
                    UnresolvableHint::HINT_TYPE_RETURN
                );

                $this->logUnresolvableChildReturn(
                    $function_name,
                    $analyzed_function->getClass()->getFqcn(),
                    $child_return,
                    $analyzed_parent_function->getClass()->getFqcn() ?? 'none',
                    $parent_return
                );
            }
        }

        return $this->removeInstructions($instructions, $unresolvable_hints);
    }

    /**
     * Takes a set of Instructions and UnresolvableHints and removes Instructions based
     * on the UnresolvableHints.
     *
     * @param AbstractInstruction[] $instructions
     * @param UnresolvableHint[] $unresolvable_hints
     * @return AbstractInstruction[]
     */
    private function removeInstructions(array $instructions, array $unresolvable_hints): array
    {
        $filtered_instructions = $instructions;

        foreach ($unresolvable_hints as $unresolvable_hint) {
            foreach ($instructions as $i => $instruction) {
                if ($instruction->getTargetFunctionName() !== $unresolvable_hint->getFunctionName()
                    || $instruction->getTargetClass()->getFqcn() !== $unresolvable_hint->getClass()->getFqcn()
                ) {
                    continue;
                }

                if ($instruction instanceof TypeHintInstruction
                    && $unresolvable_hint->getHintType() === UnresolvableHint::HINT_TYPE_PARAMETER
                    && $instruction->getTargetArgNumber() === $unresolvable_hint->getArgumentNumber()
                ) {
                    unset($filtered_instructions[$i]);
                    continue;
                }

                if ($instruction instanceof ReturnTypeInstruction
                    && $unresolvable_hint->getHintType() === UnresolvableHint::HINT_TYPE_RETURN
                ) {
                    unset($filtered_instructions[$i]);
                }
            }
        }

        return $filtered_instructions;
    }

    /**
     * Returns whether an array of PhpTypes only contains scalar types.
     *
     * @param PhpTypeInterface[] $types
     * @return bool
     */
    private function containsOnlyScalars(array $types): bool
    {
        foreach ($types as $type) {
            if (!$type instanceof ScalarPhpType) {
                return false;
            }
        }
        return true;
    }

    /**
     * Removes all duplicate entries in an array of PhpTypes.
     *
     * @param PhpTypeInterface[] $php_types
     * @return PhpTypeInterface[]
     */
    private function filterPhpTypes(array $php_types): array
    {
        $unique_types = [];

        foreach ($php_types as $type) {
            $unique_types[$type->getName()] = $type;
        }

        return array_values($unique_types);
    }

    /**
     * Returns whether a given path is within a given folder in the target
     * project directory.
     *
     * @param string $full_path
     * @param string $folder
     * @return bool
     */
    private function isInFolder(string $full_path, string $folder): bool
    {
        return strpos($full_path, $this->target_project . '/' . $folder . '/') !== false;
    }

    /**
     * Returns whether at least one AnalyzedClass in the given array is a
     * vendor class.
     *
     * @param AnalyzedClass[] $analyzed_classes
     * @return bool
     */
    private function containsVendorClass(array $analyzed_classes): bool
    {
        foreach ($analyzed_classes as $analyzed_class) {
            if ($this->isInFolder($analyzed_class->getFullPath(), self::VENDOR_FOLDER)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param AnalyzedFunction $analyzed_function
     * @param PhpTypeInterface[] $used_types
     * @param int $arg_number
     */
    private function logInconsistentParamType(AnalyzedFunction $analyzed_function, array $used_types, int $arg_number)
    {
        $used_type_names = [];
        foreach ($used_types as $type) {
            $used_type_names[] = $type->getName();
        }

        $this->logger->warning(
            "TYPE_HINT: Inconsistent types used for argument {arg_nr} in function '{fqcn}::{function}': {types}.",
            [
                'arg_nr' => $arg_number,
                'function' => $analyzed_function->getFunctionName(),
                'fqcn' => $analyzed_function->getClass()->getFqcn(),
                'types' => implode(', ', $used_type_names)
            ]
        );
    }

    /**
     * @param AnalyzedFunction $analyzed_function
     * @param AnalyzedReturn[] $return_types
     */
    private function logInconsistentReturnType(AnalyzedFunction $analyzed_function, array $return_types)
    {
        $used_type_names = [];
        foreach ($return_types as $type) {
            $used_type_names[] = $type->getType()->getName();
        }

        $this->logger->warning(
            "RETURN_TYPE: Inconsistent return types for function '{fqcn}::{function}': {types}.",
            [
                'fqcn' => $analyzed_function->getClass()->getFqcn(),
                'function' => $analyzed_function->getFunctionName(),
                'types' => implode(', ', $used_type_names)
            ]
        );
    }

    /**
     * @param AnalyzedFunction $analyzed_function
     * @param AnalyzedClass[] $parents
     */
    private function logImmutableParent(AnalyzedFunction $analyzed_function, array $parents)
    {
        $parent_classes = [];
        foreach ($parents as $parent) {
            $parent_classes[] = $parent->getClassName();
        }

        $this->logger->warning(
            "IMMUTABLE_FUNCTION: Cannot modify '{fqcn}::{function}' because the function inherits" .
            ' from one or more vendor classes: {parents}',
            [
                'fqcn' => $analyzed_function->getClass()->getFqcn(),
                'function' => $analyzed_function->getFunctionName(),
                'parents' => implode(', ', $parent_classes)
            ]
        );
    }

    /**
     * @param string $function_name
     * @param string $parent_fqcn
     * @param string $parent_return
     * @param string $child_fqcn
     * @param string $child_return
     */
    private function logUnresolvableParentReturn(
        string $function_name,
        string $parent_fqcn,
        string $parent_return = null,
        string $child_fqcn,
        string $child_return = null
    ) {
        $this->logger->warning(
            "RETURN_TYPE_COVARIANCE: Cannot add return type to parent class '{parent_fqcn}::" .
            "{function}' due to its child '{child_fqcn}::{function}' returning different types. " .
            "Parent returns: '{parent_type}', child returns: '{child_type}'",
            [
                'function' => $function_name,
                'parent_fqcn' => $parent_fqcn,
                'parent_type' => $parent_return,
                'child_fqcn' => $child_fqcn,
                'child_type' => $child_return
            ]
        );
    }

    /**
     * @param string $fqcn
     * @param string $function_name
     * @param int $arg_nr
     */
    private function logUnresolvableParentTypeHint(string $fqcn, string $function_name, int $arg_nr)
    {
        $this->logger->warning(
            "TYPE_HINT_COVARIANCE: Cannot add parameter type hints to '{fqcn}::{function}' (argument " .
            '{arg_nr}) and its children due to inconsistent parameters types.',
            [
                'fqcn' => $fqcn,
                'function' => $function_name,
                'arg_nr' => $arg_nr
            ]
        );
    }

    /**
     * @param string $function_name
     * @param string $child_fqcn
     * @param string $child_return
     * @param string $parent_fqcn
     * @param string $parent_return
     */
    private function logUnresolvableChildReturn(
        string $function_name,
        string $child_fqcn,
        string $child_return = null,
        string $parent_fqcn,
        string $parent_return = null
    ) {
        $this->logger->warning(
            "RETURN_TYPE_COVARIANCE: Cannot add return type to child class '{child_fqcn}::" .
            "{function}' due to it not being compatible with its parent '{parent_fqcn}::" .
            "{function}' return type. Child returns: '{child_type}', parent returns: '{parent_type}'",
            [
                'function' => $function_name,
                'child_fqcn' => $child_fqcn,
                'child_type' => $child_return,
                'parent_fqcn' => $parent_fqcn,
                'parent_type' => $parent_return
            ]
        );
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

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
