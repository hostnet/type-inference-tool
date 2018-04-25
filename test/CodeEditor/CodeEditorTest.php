<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\CodeEditor;

use Hostnet\Component\TypeInference\CodeEditor\Instruction\AbstractInstruction;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\CodeEditor\CodeEditor
 */
class CodeEditorTest extends TestCase
{
    /**
     * @var CodeEditor
     */
    private $code_editor;
    private $target_project = 'just/some/project';

    protected function setUp()
    {
        $this->code_editor = new CodeEditor();
    }

    public function testGivenInstructionsAreAppliedToTargetProject()
    {
        $instruction_0 = $this->createMock(AbstractInstruction::class);
        $instruction_0->expects($this->exactly(1))->method('apply')->with($this->target_project);

        $instruction_1 = $this->createMock(AbstractInstruction::class);
        $instruction_1->expects($this->exactly(1))->method('apply')->with($this->target_project);

        $this->code_editor->setInstructions([$instruction_0, $instruction_1]);
        $this->code_editor->applyInstructions($this->target_project);
    }

    public function testGetAppliedInstructionsShouldReturnAllSucceededInstructions()
    {
        $instruction_success = $this->createMock(AbstractInstruction::class);
        $instruction_success->expects($this->exactly(1))
            ->method('apply')
            ->with($this->target_project)
            ->willReturn(true);

        $instruction_fail = $this->createMock(AbstractInstruction::class);
        $instruction_fail->expects($this->exactly(1))
            ->method('apply')
            ->with($this->target_project)
            ->willReturn(false);

        $this->code_editor->setInstructions([$instruction_success, $instruction_fail]);
        $this->code_editor->applyInstructions($this->target_project);

        $success_instruction = $this->code_editor->getAppliedInstructions();
        self::assertCount(1, $success_instruction);
    }

    public function testDiffHandlerShouldBePassedToInstructions()
    {
        $handler = function () {
            // Some callback
        };
        $instruction = $this->createMock(AbstractInstruction::class);
        $instruction->expects($this->exactly(1))->method('apply')->with($this->target_project, $handler);

        $this->code_editor->setDiffHandler($handler);
        $this->code_editor->setInstructions([$instruction]);
        $this->code_editor->applyInstructions($this->target_project);
    }
}
