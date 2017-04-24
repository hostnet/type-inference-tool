<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Tool;

use PHPUnit\Framework\TestCase;
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
     * @var string
     */
    private $log_dir;

    protected function setUp()
    {
        $tool = new Tool();

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
        $target_project = 'Some/Project/Directory';
        $this->command_tester->execute([Tool::ARG_TARGET => $target_project]);
        $output = $this->command_tester->getDisplay();

        self::assertContains('Started analysing ' . $target_project, $output);
        self::assertContains('Applying generated instructions', $output);
    }

    public function testExecuteAnalyzeOnlyWithTarget()
    {
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
        $this->command_tester->execute([
            Tool::ARG_TARGET => 'Some/Project/Directory',
            '--' . Tool::OPTION_LOG_DIR[0] =>  $this->log_dir
        ]);

        self::assertFileExists($this->log_dir);
    }
}
