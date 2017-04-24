<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\CodeEditor\Instruction;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\PhpTypeInterface;
use Hostnet\Component\TypeInference\CodeEditor\CodeEditorFile;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
     * @var PhpTypeInterface
     */
    private $target_type_hint;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param AnalyzedClass $class
     * @param string $function_name
     * @param int $arg_number
     * @param PhpTypeInterface $type_hint
     * @param LoggerInterface $logger
     */
    public function __construct(
        AnalyzedClass $class,
        string $function_name,
        int $arg_number,
        PhpTypeInterface $type_hint,
        LoggerInterface $logger = null
    ) {
        parent::__construct($class, $function_name);
        $this->target_arg_number = $arg_number;
        $this->target_type_hint  = $type_hint;
        $this->logger            = $logger ?? new NullLogger();
    }

    /**
     * Adds the given type hint to the parameter in the project file in which the
     * function is declared.
     *
     * @param string $target_project
     * @param callable $diff_handler
     * @param bool $overwrite_file
     * @return bool Success indication
     */
    public function apply(string $target_project, callable $diff_handler = null, bool $overwrite_file = true): bool
    {
        try {
            $file_to_modify = $this->retrieveFileToModify($target_project);
            $updated_file   = $this->insertTypeHint($file_to_modify);
            $this->saveFile($updated_file, $diff_handler, $overwrite_file);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Inserts a type hint on the correct position in the file.
     *
     * @param CodeEditorFile $file
     * @return CodeEditorFile
     * @throws \RuntimeException
     */
    private function insertTypeHint(CodeEditorFile $file): CodeEditorFile
    {
        $type                = $this->target_type_hint;
        $type_representation = $type instanceof NonScalarPhpType ? $type->getClassName() : $type->getName();

        $pattern      = sprintf(
            '/function %s\((\n\s*)?((\w+\s)?(\$|&)\w+,(\s|\n)(\s*)?){%s}(?!(\s*)?\\\\?\w+)+/',
            $this->getTargetFunctionName(),
            $this->target_arg_number
        );
        $replacement  = sprintf('$0%s ', $type_representation);
        $updated_file = preg_replace($pattern, $replacement, $file->getContents());

        if (strcmp($updated_file, $file->getContents()) === 0) {
            throw new \RuntimeException('Could not add type hint, there might already be one.');
        }

        $file->setContents($updated_file);
        $this->logger->debug('TYPE_HINT: Added {type} to parameter {param_nr} in {fqcn}::{function}', [
            'type' => $type_representation,
            'param_nr' => $this->target_arg_number,
            'fqcn' => $this->getTargetClass()->getFqcn(),
            'function' => $this->getTargetFunctionName()
        ]);

        return $file;
    }
}
