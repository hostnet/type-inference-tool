<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\Data\Exception\EntryNotFoundException;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\AbstractInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\ReturnTypeInstruction;

/**
 * Holds a traversable collection of {@link AnalyzedFunction}.
 */
class AnalyzedFunctionCollection implements \Iterator
{
    /**
     * @var AnalyzedFunction[]
     */
    private $analyzed_functions = [];

    /**
     * Different AnalyzedFunction with the same class-property should have their class
     * pointed to the same object, this array holds these classes.
     *
     * @var AnalyzedClass[] [fqnc => AnalyzedClass]
     */
    private $shared_classes = [];

    /**
     * Current position in the iterator.
     *
     * @var int
     */
    private $position = 0;

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
            $this->add($function);
        }
    }

    /**
     * Adds an AnalyzedFunction to the collection.
     *
     * @param AnalyzedFunction $analyzed_function
     */
    public function add(AnalyzedFunction $analyzed_function)
    {
        $fqcn = $analyzed_function->getClass()->getFqcn();

        if (!array_key_exists($fqcn, $this->shared_classes)) {
            $this->shared_classes[$fqcn] = $analyzed_function->getClass();
        } else {
            $this->updateSharedClass($analyzed_function);
        }

        $this->shared_classes[$fqcn]->addMethod($analyzed_function->getFunctionName());
        $analyzed_function->setClass($this->shared_classes[$fqcn]);

        try {
            $existing_analyzed_function = $this->get($fqcn, $analyzed_function->getFunctionName());
            $existing_analyzed_function->addAllCollectedArguments($analyzed_function->getCollectedArguments());
            $existing_analyzed_function->addAllCollectedReturns($analyzed_function->getCollectedReturns());
        } catch (EntryNotFoundException $e) {
            $this->analyzed_functions[] = $analyzed_function;
        }

        $this->referenceExtendedClasses();
        $this->referenceImplementedClasses();
    }

    /**
     * The classes from the AnalyzedFunctions in the array with AnalyzedFunctions,
     * contain the full definitions of the class. Extended classes, however, do not
     * always contain the full definition of a class, they could be incomplete. This
     * function makes sure that the extended classes reference to the classes with
     * the full definition.
     */
    private function referenceExtendedClasses()
    {
        foreach ($this->analyzed_functions as $analyzed_function) {
            $extended_class = $analyzed_function->getClass()->getExtends();

            if ($extended_class === null) {
                continue;
            }

            try {
                $analyzed_function->getClass()->setExtends($this->findClass($extended_class->getFqcn()));
            } catch (EntryNotFoundException $e) {
                continue;
            }
        }
    }

    /**
     * Full class definitions are defined in the array with AnalyzedFunctions. The
     * implemented classes, however, do not always contain the full definition of
     * the class, they could be incomplete. This function makes sure that the
     * implemented classes point to the full class definition.
     */
    private function referenceImplementedClasses()
    {
        foreach ($this->analyzed_functions as $analyzed_function) {
            foreach ($analyzed_function->getClass()->getImplements() as $i => $implemented_class) {
                try {
                    $full_class = $this->findClass($implemented_class->getFqcn());
                } catch (EntryNotFoundException $e) {
                    continue;
                }

                $implemented_classes     = $analyzed_function->getClass()->getImplements();
                $implemented_classes[$i] = $full_class;
                $analyzed_function->getClass()->setImplements($implemented_classes);
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
    public function get(string $fqcn, string $function_name): AnalyzedFunction
    {
        foreach ($this->analyzed_functions as $function) {
            if ($function->getFunctionName() === $function_name && $function->getClass()->getFqcn() === $fqcn) {
                return $function;
            }
        }

        throw new EntryNotFoundException(sprintf('AnalyzedFunction %s::%s does not exist', $fqcn, $function_name));
    }

    /**
     * Searches the AnalyzedFunctions for a class by the given fully qualified
     * class name.
     *
     * @param string $fqcn
     * @return AnalyzedClass
     * @throws EntryNotFoundException
     */
    private function findClass(string $fqcn): AnalyzedClass
    {
        foreach ($this->analyzed_functions as $analyzed_function) {
            if ($fqcn === $analyzed_function->getClass()->getFqcn()) {
                return $analyzed_function->getClass();
            }
        }

        throw new EntryNotFoundException(sprintf('Class %s does not exist', $fqcn));
    }

    /**
     * Takes an array of instructions and applies them to the AnalyzedFunctions.
     * This is used to analyse the resulting AnalyzedFunctions after applying the
     * instructions to determine whether the resulting functions are valid.
     *
     * @param AbstractInstruction[] $instructions
     */
    public function applyInstructions(array $instructions)
    {
        foreach ($instructions as $instruction) {
            if ($instruction instanceof ReturnTypeInstruction) {
                $this->applyReturnTypeInstruction($instruction);
            }
        }
    }

    /**
     * Adds a return type to an AnalyzedFunctions by applying an instruction. This is
     * used to analyse the results after applying an instruction.
     *
     * @param ReturnTypeInstruction $instruction
     */
    private function applyReturnTypeInstruction(ReturnTypeInstruction $instruction)
    {
        foreach ($this->analyzed_functions as $function) {
            if (!$function->hasReturnDeclaration()
                && $function->getFunctionName() === $instruction->getTargetFunctionName()
                && $function->getClass()->getFqcn() === $instruction->getTargetClass()->getFqcn()
            ) {
                $function->setDefinedReturnType($instruction->getTargetReturnType()->getName());
            }
        }
    }

    /**
     * Takes the class from the given AnalyzedFunctions and updates the existing
     * entry in the shared classes array. The shared classes array is used to
     * reference to from AnalyzedFunctions.
     *
     * @param AnalyzedFunction $analyzed_function
     */
    private function updateSharedClass(AnalyzedFunction $analyzed_function)
    {
        $class        = $analyzed_function->getClass();
        $fqcn         = $class->getFqcn();
        $shared_class = $this->shared_classes[$fqcn];

        if ($class->getFullPath() !== null) {
            $shared_class->setFullPath($analyzed_function->getClass()->getFullPath());
        }

        if ($class->getExtends() !== null) {
            $shared_class->setExtends($analyzed_function->getClass()->getExtends());
        }

        if (count($class->getImplements()) > 0) {
            foreach ($class->getImplements() as $implement) {
                $shared_class->addImplementedClass($implement);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->analyzed_functions[$this->position];
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        return isset($this->analyzed_functions[$this->position]);
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * @return AnalyzedFunction[]
     */
    public function getAll(): array
    {
        return $this->analyzed_functions;
    }
}
