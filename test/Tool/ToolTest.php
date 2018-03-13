<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Tool;

use Doctrine\DBAL\DriverManager;
use Hostnet\Component\DatabaseTest\MysqlPersistentConnection;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\DynamicAnalyzer;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\DatabaseRecordStorage;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\FileRecordStorage;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\MemoryRecordStorage;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\RecordStorageInterface;
use Hostnet\Component\TypeInference\Analyzer\ProjectAnalyzer;
use Hostnet\Component\TypeInference\Analyzer\StaticMethod\StaticAnalyzer;
use Hostnet\Component\TypeInference\CodeEditor\CodeEditor;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\ReturnTypeInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\TypeHintInstruction;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\NullLogger;
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
     * @var DatabaseRecordStorage
     */
    private static $storage;

    /**
     * @var string
     */
    private static $db_config_file;

    /**
     * @var MysqlPersistentConnection
     */
    private static $conn;

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
        $this->log_dir        = __DIR__ . '/output/logs' . uniqid('', false) . '.log';
    }

    protected function tearDown()
    {
        $fs = new Filesystem();
        $fs->remove($this->log_dir);
    }

    public static function setUpBeforeClass()
    {
        self::$conn = new MysqlPersistentConnection();

        $definition_script = file_get_contents(dirname(__DIR__, 2) . '/database/DDL.sql');
        $connection        = DriverManager::getConnection(self::$conn->getConnectionParams());
        $connection->getDatabasePlatform()->registerDoctrineTypeMapping('bit', 'boolean');
        $connection->exec($definition_script);

        self::$db_config_file = dirname(__DIR__) . '/Fixtures/' . uniqid('db-config-test-', false) . '.json';
        file_put_contents(self::$db_config_file, json_encode(self::$conn->getConnectionParams()));

        self::$storage = new DatabaseRecordStorage(self::$db_config_file);
    }

    public static function tearDownAfterClass()
    {
        unlink(self::$db_config_file);
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
            Tool::ARG_TARGET                => 'Some/Project/Directory',
            '--' . Tool::OPTION_LOG_FILE[0] =>  $this->log_dir
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

    public function testExecuteWithShowDiffsShouldHaveADiffSection()
    {
        $this->project_analyzer = new ProjectAnalyzer();
        $this->code_editor      = new CodeEditor();
        $tool                   = new Tool($this->project_analyzer, $this->code_editor);

        $this->application = new Application();
        $this->application->add($tool);

        $this->command        = $this->application->find(Tool::EXECUTE_COMMAND);
        $this->command_tester = new CommandTester($this->command);
        $this->log_dir        = __DIR__ . '/output/logs.log';

        $this->command_tester->execute([
            Tool::ARG_TARGET => dirname(__DIR__) . '/Fixtures/ExampleDynamicAnalysis/Example-Project-1/',
            '--' . Tool::OPTION_SHOW_DIFF[0] => true,
            '--' . Tool::OPTION_ANALYSE_ONLY[0] => true
        ]);
        $output = $this->command_tester->getDisplay();
        self::assertContains("Diffs\n-----", $output);

        $expected_diff = "-    private function executeCallback(\$cb)\n"
            . '+    private function executeCallback(callable $cb)';
        self::assertContains($expected_diff, $output);
    }

    /**
     * @dataProvider recordStorageProvider
     * @param string $storage_type
     * @param RecordStorageInterface $storage
     */
    public function testWhenStorageTypeSetThenUseThatStorageType(
        string $storage_type,
        RecordStorageInterface $storage
    ) {
        $expected_analyzer = new DynamicAnalyzer($storage, [ProjectAnalyzer::VENDOR_FOLDER], new NullLogger());
        $this->project_analyzer
            ->expects(self::exactly(2))
            ->method('addAnalyzer')
            ->withConsecutive(
                [self::equalTo($expected_analyzer, 0, 10, false)],
                [self::isInstanceOf(StaticAnalyzer::class)]
            );

        $this->command_tester->execute([
            Tool::ARG_TARGET => 'Some/Project/Directory',
            '--' . Tool::OPTION_STORAGE_TYPE[0] => $storage_type
        ]);
    }

    /**
     * @dataProvider invalidRecordStorageProvider
     * @param string $storage_type
     */
    public function testWhenInvalidStorageTypeIsSetItShouldThrowAnError(string $storage_type)
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->command_tester->execute([
            Tool::ARG_TARGET => 'Some/Project/Directory',
            '--' . Tool::OPTION_STORAGE_TYPE[0] => $storage_type
        ]);
    }

    public function testWhenDatabaseStorageTypeIsSetThenUseDatabaseStorage()
    {
        $this->project_analyzer
            ->expects(self::exactly(2))
            ->method('addAnalyzer')
            ->withConsecutive(
                [self::equalTo(
                    new DynamicAnalyzer(self::$storage, [ProjectAnalyzer::VENDOR_FOLDER], new NullLogger()),
                    0,
                    10,
                    false
                )],
                [self::isInstanceOf(StaticAnalyzer::class)]
            );

        $this->command_tester->execute([
            Tool::ARG_TARGET => 'Some/Project/Directory',
            '--' . Tool::OPTION_STORAGE_TYPE[0] => Tool::STORAGE_TYPE_DATABASE,
            '--' . Tool::OPTION_DATABASE_CONFIG[0] => self::$db_config_file
        ]);
    }

    public function testWhenFolderIsMarkedAsIgnoredItShouldNotBeAnalyzed()
    {
        $this->project_analyzer = new ProjectAnalyzer();
        $this->code_editor      = new CodeEditor();
        $tool                   = new Tool($this->project_analyzer, $this->code_editor);

        $this->application = new Application();
        $this->application->add($tool);

        $this->command        = $this->application->find(Tool::EXECUTE_COMMAND);
        $this->command_tester = new CommandTester($this->command);

        $this->command_tester->execute([
            Tool::ARG_TARGET => dirname(__DIR__) . '/Fixtures/ExampleDynamicAnalysis/Example-Project-1/',
            '--' . Tool::OPTION_ANALYSE_ONLY[0] => true,
            '--' . Tool::OPTION_IGNORE_FOLDERS[0] => 'src'
        ]);

        $output           = $this->command_tester->getDisplay();
        $expected_results =
            '  Return types   Type hints   Total  ' . PHP_EOL .
            ' -------------- ------------ ------- ' . PHP_EOL .
            '  0              0            0      ';

        self::assertContains($expected_results, $output);
    }

    public function recordStorageProvider(): array
    {
        return [
            [Tool::STORAGE_TYPE_FILE, new FileRecordStorage()],
            [Tool::STORAGE_TYPE_MEMORY, new MemoryRecordStorage()]
        ];
    }

    public function invalidRecordStorageProvider(): array
    {
        return [
            [Tool::STORAGE_TYPE_DATABASE],
            ['Invalid value']
        ];
    }
}
