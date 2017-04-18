<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\CodeEditor\Instruction;

use Hostnet\Component\TypeInference\Analyzer\Data\PhpType;
use Hostnet\Component\TypeInference\CodeEditor\CodeEditorFile;

/**
 * Instruction used to add a type hint to a parameter in a function declaration.
 */
final class TypeHintInstruction extends AbstractInstruction
{
    /**
     * @var int
     */
    private $target_arg_number;

    /**
     * @var PhpType
     */
    private $target_type_hint;

    /**
     * @param string $namespace
     * @param string $class_name
     * @param string $function_name
     * @param int $arg_number
     * @param PhpType $type_hint
     */
    public function __construct(
        string $namespace,
        string $class_name,
        string $function_name,
        int $arg_number,
        PhpType $type_hint
    ) {
        parent::__construct($namespace, $class_name, $function_name);
        $this->target_arg_number = $arg_number;
        $this->target_type_hint  = $type_hint;
    }

    /**
     * Adds the given type hint to the parameter in the project file in which the
     * function is declared.
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

        $updated_file = $this->insertTypeHint($file_to_modify);
        $this->saveFile($updated_file);
    }

    /**
     * Inserts a type hint on the correct position in the file.
     *
     * @param CodeEditorFile $file
     * @return CodeEditorFile
     */
    private function insertTypeHint(CodeEditorFile $file): CodeEditorFile
    {
        $pattern     = sprintf(
            '/function %s\((\n\s*)?((\$|&)\w+,(\s|\n)(\s*)?){%s}/',
            $this->getTargetFunctionName(),
            $this->target_arg_number
        );
        $replacement = sprintf('$0%s ', $this->target_type_hint->getName());
        $file->setContents(preg_replace($pattern, $replacement, $file->getContents()));

        return $file;
    }
}
