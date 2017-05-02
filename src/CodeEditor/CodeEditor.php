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
     * @var AbstractInstruction[]
     */
    private $instructions = [];

    /**
     * Successful applied instructions.
     *
     * @var AbstractInstruction[]
     */
    private $applied_instructions = [];

    /**
     * @var callable
     */
    private $diff_handler;

    /**
     * Applies all given instructions. These instructions modify the source-code
     * of the given target project.
     *
     * @param string $target_project
     * @param bool $overwrite_files
     */
    public function applyInstructions(string $target_project, bool $overwrite_files = true)
    {
        foreach ($this->instructions as $instruction) {
            if ($instruction->apply($target_project, $this->diff_handler, $overwrite_files)) {
                $this->applied_instructions[] = $instruction;
            }
        }
    }

    /**
     * Returns the successfully applied instructions.
     *
     * @return AbstractInstruction[]
     */
    public function getAppliedInstructions(): array
    {
        return $this->applied_instructions;
    }

    /**
     * @param AbstractInstruction[] $instructions
     */
    public function setInstructions(array $instructions)
    {
        $this->instructions = $instructions;
    }

    /**
     * Sets a callback so the instructions can output diffs
     * to the console to show the modifications to a file.
     *
     * @param callable $handler
     */
    public function setDiffHandler(callable $handler)
    {
        $this->diff_handler = $handler;
    }
}
