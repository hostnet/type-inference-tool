<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\CodeEditor\Instruction;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\PhpTypeInterface;
use Hostnet\Component\TypeInference\CodeEditor\CodeEditorFile;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param AnalyzedClass $class
     * @param string $function_name
     * @param PhpTypeInterface $return_type
     * @param LoggerInterface $logger
     */
    public function __construct(
        AnalyzedClass $class,
        string $function_name,
        PhpTypeInterface $return_type,
        LoggerInterface $logger = null
    ) {
        parent::__construct($class, $function_name);
        $this->target_return_type = $return_type;
        $this->logger             = $logger ?? new NullLogger();
    }

    /**
     * Adds the given return type to the project file in which the function
     * is declared.
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
            $updated_file   = $this->insertReturnType($file_to_modify);
            $this->saveFile($updated_file, $diff_handler, $overwrite_file);
        } catch (\RuntimeException $e) {
            return false;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
        return true;
    }

    /**
     * Adds a return type declaration to the correct position in a file.
     *
     * @param CodeEditorFile $file
     * @return CodeEditorFile Updated file
     * @throws \RuntimeException
     */
    private function insertReturnType(CodeEditorFile $file): CodeEditorFile
    {
        $type                = $this->target_return_type;
        $type_representation = $type instanceof NonScalarPhpType ? $type->getClassName() : $type->getName();
        $updated_file        = preg_replace_callback(
            sprintf(
                '/function %s\([\n\s\w\W]*?\)(?=(:[\\\\\w\?\s]+)?\s*(?=[{;]))/',
                preg_quote($this->getTargetFunctionName(), '/')
            ),
            function ($matches) use ($type_representation, $type) {
                if (count($matches) > 1) {
                    return $matches[0];
                }

                return $matches[0] . ': ' . ($type->isNullable() ? '?' : '') . $type_representation;
            },
            $file->getContents()
        );

        if (strcmp($updated_file, $file->getContents()) === 0) {
            throw new \RuntimeException('Could not add return type declaration, there might already be one.');
        }

        $file->setContents($updated_file);
        $this->logger->debug('RETURN_TYPE: Added {type} to {fqcn}::{function}', [
            'type' => ($type->isNullable() ? '?' : '') . $type_representation,
            'fqcn' => $this->getTargetClass()->getFqcn(),
            'function' => $this->getTargetFunctionName(),
        ]);

        return $file;
    }

    /**
     * @return PhpTypeInterface
     */
    public function getTargetReturnType(): PhpTypeInterface
    {
        return $this->target_return_type;
    }
}
