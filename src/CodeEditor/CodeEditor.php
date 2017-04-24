<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\CodeEditor;

use Hostnet\Component\TypeInference\CodeEditor\Instruction\AbstractInstruction;

/**
 * Takes a list of instructions and applies them. These instructions should
 * modify the source-code of a target project.
 */
class CodeEditor
{
    /**
     * @var string
     */
    private $target_project;

    /**
     * @var AbstractInstruction[]
     */
    private $instructions = [];

    /**
     * @param string $target_project
     */
    public function __construct(string $target_project)
    {
        $this->target_project = $target_project;
    }

    /**
     * Applies all given instructions. These instructions modify the source-code
     * of the given target project.
     */
    public function applyInstructions()
    {
        foreach ($this->instructions as $instruction) {
            $instruction->apply($this->target_project);
        }
    }

    /**
     * @param AbstractInstruction[] $instructions
     */
    public function setInstructions(array $instructions)
    {
        $this->instructions = $instructions;
    }
}
