<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\CodeEditor\Instruction;

use Hostnet\Component\TypeInference\CodeEditor\CodeEditorFile;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Abstract class used for instructions. Instruction modify projects
 * source-files.
 */
abstract class AbstractInstruction
{
    /**
     * @var string
     */
    private $target_namespace;

    /**
     * @var string
     */
    private $target_class_name;

    /**
     * @var string
     */
    private $target_function_name;

    /**
     * @param string $target_namespace
     * @param string $target_class_name
     * @param string $target_function_name
     */
    public function __construct(string $target_namespace, string $target_class_name, string $target_function_name)
    {
        $this->target_namespace     = $target_namespace;
        $this->target_class_name    = $target_class_name;
        $this->target_function_name = $target_function_name;
    }

    /**
     * Applies the instruction to the target project.
     *
     * @param string $target_project
     */
    abstract public function apply(string $target_project);

    /**
     * Searches a target project for the source-file containing the function
     * within a class and namespace.
     *
     * @param string $target_project
     * @return CodeEditorFile
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function retrieveFileToModify(string $target_project): CodeEditorFile
    {
        $finder = new Finder();
        $finder->files()->in($target_project)->exclude('vendor');

        foreach ($finder as $file) {
            $file_contents = $file->getContents();

            if (strpos($file_contents, sprintf('function %s(', $this->target_function_name)) !== false
                && strpos($file_contents, sprintf('class %s', $this->target_class_name)) !== false
                && strpos($file_contents, sprintf('namespace %s;', $this->target_namespace)) !== false
            ) {
                return new CodeEditorFile($file->getRealPath(), $file_contents);
            }
        }

        throw new \InvalidArgumentException('Function not found in target project');
    }

    /**
     * Saves the contents of the CodeEditorFile to its path, thus updating
     * the original file.
     *
     * @param CodeEditorFile $file
     * @throws IOException
     */
    protected function saveFile(CodeEditorFile $file)
    {
        $fs = new Filesystem();
        $fs->dumpFile($file->getPath(), $file->getContents());
    }

    /**
     * @return string
     */
    protected function getTargetFunctionName(): string
    {
        return $this->target_function_name;
    }
}
