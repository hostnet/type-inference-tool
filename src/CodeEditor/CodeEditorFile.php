<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\CodeEditor;

/**
 * Represents a source-file.
 */
class CodeEditorFile
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $contents;

    /**
     * @param string $path
     * @param string $contents
     */
    public function __construct(string $path, string $contents)
    {
        $this->path     = $path;
        $this->contents = $contents;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getContents(): string
    {
        return $this->contents;
    }

    /**
     * @param string $contents
     */
    public function setContents(string $contents)
    {
        $this->contents = $contents;
    }
}
