<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\CodeEditor;

use Hostnet\Component\TypeInference\CodeEditor\Instruction\AbstractInstruction;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\CodeEditor\CodeEditor
 */
class CodeEditorTest extends TestCase
{
    public function testGivenInstructionsAreAppliedToTargetProject()
    {
        $target_project = 'just/some/project/';
        $code_editor    = new CodeEditor($target_project);

        $instruction_0 = $this->createMock(AbstractInstruction::class);
        $instruction_0->expects($this->exactly(1))->method('apply')->with($target_project);

        $instruction_1 = $this->createMock(AbstractInstruction::class);
        $instruction_1->expects($this->exactly(1))->method('apply')->with($target_project);

        $code_editor->setInstructions([$instruction_0, $instruction_1]);

        $code_editor->applyInstructions($target_project);
    }
}
