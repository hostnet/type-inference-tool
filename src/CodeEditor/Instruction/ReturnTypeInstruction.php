<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\CodeEditor\Instruction;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\PhpTypeInterface;
use Hostnet\Component\TypeInference\CodeEditor\CodeEditorFile;

/**
 * Instruction used to add a return type to a function declaration.
 */
final class ReturnTypeInstruction extends AbstractInstruction
{
    /**
     * @var PhpTypeInterface
     */
    private $target_return_type;

    /**
     * @param AnalyzedClass $class
     * @param string $function_name
     * @param PhpTypeInterface $return_type
     */
    public function __construct(AnalyzedClass $class, string $function_name, PhpTypeInterface $return_type)
    {
        parent::__construct($class, $function_name);
        $this->target_return_type = $return_type;
    }

    /**
     * Adds the given return type to the project file in which the function
     * is declared.
     *
     * @param string $target_project
     * @throws \RuntimeException
     */
    public function apply(string $target_project)
    {
        try {
            $file_to_modify = $this->retrieveFileToModify($target_project);
        } catch (\InvalidArgumentException $e) {
            return;
        }

        $updated_file = $this->insertReturnType($file_to_modify);
        $this->saveFile($updated_file);
    }

    /**
     * Adds a return type declaration to the correct position in a file.
     *
     * @param CodeEditorFile $file
     * @return CodeEditorFile Updated file
     */
    private function insertReturnType(CodeEditorFile $file): CodeEditorFile
    {
        $pattern     = sprintf('/function %s\((\n.*)*.*\)/', $this->getTargetFunctionName());
        $replacement = sprintf('$0: %s', $this->target_return_type->getName());

        $file->setContents(preg_replace($pattern, $replacement, $file->getContents()));

        return $file;
    }
}
