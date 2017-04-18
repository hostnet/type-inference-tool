<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Tool;

use Hostnet\Component\TypeInference\Analyzer\ProjectAnalyzer;
use Hostnet\Component\TypeInference\CodeEditor\CodeEditor;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * @covers \Hostnet\Component\TypeInference\Tool\Tool
 */
class ToolTest extends TestCase
{
    /**
     * @var Tool
     */
    private $tool;

    /**
     * @var ProjectAnalyzer|PHPUnit_Framework_MockObject_MockObject
     */
    private $project_analyzer;

    /**
     * @var CodeEditor|PHPUnit_Framework_MockObject_MockObject
     */
    private $code_editor;

    protected function setUp()
    {
        $target_project  = 'some/path/';
        $logs_output_dir = dirname(__DIR__) . '/output/logs.log';
        $this->tool      = new Tool($target_project, $logs_output_dir);

        $this->project_analyzer = $this->createMock(ProjectAnalyzer::class);
        $this->tool->setProjectAnalyzer($this->project_analyzer);

        $this->code_editor = $this->createMock(CodeEditor::class);
        $this->tool->setCodeEditor($this->code_editor);
    }

    public function testExecuteShouldApplyInstructionFromAnalyzerToTargetProject()
    {
        $this->project_analyzer->expects($this->exactly(2))->method('addAnalyzer');
        $this->project_analyzer->expects($this->exactly(1))->method('analyse');
        $this->code_editor->expects($this->exactly(1))->method('applyInstructions');

        $this->tool->execute();
    }

    public function testExecuteShouldNotApplyInstructionsFromAnalyzerToTargetProject()
    {
        $this->project_analyzer->expects($this->exactly(2))->method('addAnalyzer');
        $this->project_analyzer->expects($this->exactly(1))->method('analyse');
        $this->code_editor->expects($this->exactly(0))->method('applyInstructions');

        $this->tool->execute(false);
    }
}
