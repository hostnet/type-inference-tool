<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Tool;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\ProjectAnalyzer;
use Hostnet\Component\TypeInference\CodeEditor\CodeEditor;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\ReturnTypeInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\TypeHintInstruction;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Hostnet\Component\TypeInference\Tool\Tool
 */
class ToolTest extends TestCase
{
    /**
     * @var Application
     */
    private $application;

    /**
     * @var Command
     */
    private $command;

    /**
     * @var CommandTester
     */
    private $command_tester;

    /**
     * @var ProjectAnalyzer|PHPUnit_Framework_MockObject_MockObject
     */
    private $project_analyzer;

    /**
     * @var CodeEditor|PHPUnit_Framework_MockObject_MockObject
     */
    private $code_editor;

    /**
     * @var string
     */
    private $log_dir;

    protected function setUp()
    {
        $this->project_analyzer = $this->createMock(ProjectAnalyzer::class);
        $this->code_editor      = $this->createMock(CodeEditor::class);
        $tool                   = new Tool($this->project_analyzer, $this->code_editor);

        $this->application = new Application();
        $this->application->add($tool);

        $this->command        = $this->application->find(Tool::EXECUTE_COMMAND);
        $this->command_tester = new CommandTester($this->command);
        $this->log_dir        = $log_dir = dirname(__DIR__) . '/Fixtures/Logs/logs.log';
    }

    protected function tearDown()
    {
        $fs = new Filesystem();
        $fs->remove($this->log_dir);
    }

    public function testExecuteWithTarget()
    {
        $this->project_analyzer->expects(self::exactly(2))->method('addAnalyzer');
        $this->project_analyzer->expects(self::exactly(1))->method('analyse')->willReturn([]);

        $target_project = 'Some/Project/Directory';
        $this->command_tester->execute([Tool::ARG_TARGET => $target_project]);
        $output = $this->command_tester->getDisplay();

        self::assertContains('Started analysing ' . $target_project, $output);
        self::assertContains('Applying generated instructions', $output);
    }

    public function testExecuteAnalyzeOnlyWithTarget()
    {
        $this->code_editor
            ->expects(self::exactly(1))
            ->method('applyInstructions')
            ->with('Some/Project/Directory', false);

        $target_project = 'Some/Project/Directory';
        $this->command_tester->execute([
            Tool::ARG_TARGET => $target_project,
            '--' . Tool::OPTION_ANALYSE_ONLY[0] => true
        ]);
        $output = $this->command_tester->getDisplay();

        self::assertNotContains('Applying generated instructions', $output);
    }

    public function testExecuteWithLoggingEnabled()
    {
        $this->project_analyzer->expects(self::exactly(1))->method('setLogger');
        $this->command_tester->execute([
            Tool::ARG_TARGET => 'Some/Project/Directory',
            '--' . Tool::OPTION_LOG_DIR[0] =>  $this->log_dir
        ]);

        self::assertFileExists($this->log_dir);
    }

    public function testExecutesShouldOutputCorrectResults()
    {
        $class  = new AnalyzedClass('Namespace', 'SomeClass', 'project/some_class.php', null, []);
        $type   = new TypeHintInstruction($class, 'fn', 0, new ScalarPhpType(ScalarPhpType::TYPE_BOOL));
        $return = new ReturnTypeInstruction($class, 'fn', new ScalarPhpType(ScalarPhpType::TYPE_FLOAT));
        $this->project_analyzer->expects(self::exactly(1))->method('analyse')->willReturn([$type, $return]);
        $this->code_editor->expects(self::exactly(1))->method('applyInstructions');
        $this->code_editor->expects(self::exactly(1))->method('getAppliedInstructions')->willReturn([$type, $return]);

        $this->command_tester->execute([Tool::ARG_TARGET => 'Some/Project/Directory']);
        $output = implode(PHP_EOL, array_map('trim', explode(PHP_EOL, $this->command_tester->getDisplay())));

        self::assertContains(
            file_get_contents(dirname(__DIR__) . '/Fixtures/ExampleOutput/example_output.txt'),
            $output
        );
    }
}
