<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Tool;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\DynamicAnalyzer;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\DatabaseRecordStorage;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\FileRecordStorage;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\MemoryRecordStorage;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\RecordStorageInterface;
use Hostnet\Component\TypeInference\Analyzer\ProjectAnalyzer;
use Hostnet\Component\TypeInference\Analyzer\StaticMethod\StaticAnalyzer;
use Hostnet\Component\TypeInference\CodeEditor\CodeEditor;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\AbstractInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\ReturnTypeInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\TypeHintInstruction;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SebastianBergmann\Diff\Differ;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Class used to start the tool. Applies dynamic- and static analysis to infer
 * parameter- and return types and add them as either a type hint or return type
 * declaration.
 */
class Tool extends Command
{
    const NAME                   = 'Type-Inference-Tool';
    const EXECUTE_COMMAND        = 'execute';
    const ARG_TARGET             = 'target';
    const OPTION_LOG_DIR         = ['log-dir', 'l'];
    const OPTION_ANALYSE_ONLY    = ['analyse-only', 'a'];
    const OPTION_SHOW_DIFF       = ['show-diff', 'd'];
    const OPTION_STORAGE_TYPE    = ['storage-type', 's'];
    const OPTION_DATABASE_CONFIG = ['db-config', 'db'];
    const OPTION_IGNORE_FOLDERS  = ['ignore-folders', 'i'];
    const OPTION_TRACE           = ['trace', 't'];

    const STORAGE_TYPE_MEMORY   = 'mem';
    const STORAGE_TYPE_DATABASE = 'db';
    const STORAGE_TYPE_FILE     = 'file';

    const STORAGE_TYPES = [
        'memory' => self::STORAGE_TYPE_MEMORY,
        'database' => self::STORAGE_TYPE_DATABASE,
        'file' => self::STORAGE_TYPE_FILE,
    ];

    const RESULTS_COLUMN_RETURN_TYPES = 'Return types';
    const RESULTS_COLUMN_TYPE_HINTS   = 'Type hints';
    const RESULTS_COLUMN_TOTAL        = 'Total';

    /**
     * Unique instance ID used to handle database concurrency.
     *
     * @var string
     */
    private static $execution_id;

    /**
     * @var CodeEditor
     */
    private $code_editor;

    /**
     * @var ProjectAnalyzer
     */
    private $project_analyzer;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * {@inheritdoc}
     *
     * @param ProjectAnalyzer $project_analyzer
     * @param CodeEditor $code_editor
     */
    public function __construct(ProjectAnalyzer $project_analyzer, CodeEditor $code_editor, string $name = null)
    {
        parent::__construct($name);

        $this->project_analyzer = $project_analyzer;
        $this->code_editor      = $code_editor;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName(self::EXECUTE_COMMAND)
            ->setDescription('Executes analysis on the given target project.')
            ->setHelp('This command allows you to execute dynamic- and static analysis on the given target project to'
                . ' infer return- and parameter types.')
            ->addArgument(self::ARG_TARGET, InputArgument::OPTIONAL, 'Target project directory', getcwd())
            ->addOption(
                self::OPTION_LOG_DIR[0],
                self::OPTION_LOG_DIR[1],
                InputOption::VALUE_REQUIRED,
                'Enable and save logs to the given directory'
            )
            ->addOption(
                self::OPTION_ANALYSE_ONLY[0],
                self::OPTION_ANALYSE_ONLY[1],
                InputOption::VALUE_NONE,
                'Execute analysis without modifying the target project files'
            )
            ->addOption(
                self::OPTION_SHOW_DIFF[0],
                self::OPTION_SHOW_DIFF[1],
                InputOption::VALUE_NONE,
                'Show diffs after modifying target project files.'
            )
            ->addOption(
                self::OPTION_STORAGE_TYPE[0],
                self::OPTION_STORAGE_TYPE[1],
                InputOption::VALUE_REQUIRED,
                'Define the storage type data used internally by the application. mem=Memory (default) db=Database (you'
                . ' must provide database config) file=External file',
                self::STORAGE_TYPE_MEMORY
            )
            ->addOption(
                self::OPTION_DATABASE_CONFIG[0],
                self::OPTION_DATABASE_CONFIG[1],
                InputOption::VALUE_OPTIONAL,
                'In case the storage type has been set for database, a database configuration file must be provided.'
            )
            ->addOption(
                self::OPTION_IGNORE_FOLDERS[0],
                self::OPTION_IGNORE_FOLDERS[1],
                InputOption::VALUE_REQUIRED,
                "Specify folders to be ignored during analysis (comma separated, e.g.: 'folder1,folder2,etc'). "
                . 'The vendor folder is always ignored.'
            )
            ->addOption(
                self::OPTION_TRACE[0],
                self::OPTION_TRACE[1],
                InputOption::VALUE_REQUIRED,
                'Specify existing trace to use during dynamic analysis. By specifying a trace, dynamic analysis won\'t'
                . ' generate a new one.'
            );
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start(self::NAME);

        $this->io = new SymfonyStyle($input, $output);
        $this->io->title(self::NAME);

        $target_project = $input->getArgument(self::ARG_TARGET);
        $logger         = $this->getLogger($input->getOption(self::OPTION_LOG_DIR[0]));
        $logger->info(
            "Type-Inference-Tool started for {project} (execution_id: '{id}')",
            [
                'project' => $target_project,
                'id' => self::getExecutionId()
            ]
        );

        $storage = $this->getDataStorageType(
            $input->getOption(self::OPTION_STORAGE_TYPE[0]),
            $input->getOption(self::OPTION_DATABASE_CONFIG[0])
        );

        $ignored_folders      = [ProjectAnalyzer::VENDOR_FOLDER];
        $user_ignored_folders = $input->getOption(self::OPTION_IGNORE_FOLDERS[0]);

        if ($user_ignored_folders !== null) {
            $ignored_folders = array_merge($ignored_folders, explode(',', $user_ignored_folders));
            $this->project_analyzer->setIgnoredFolders($ignored_folders);
        }

        $modification_instructions = $this->analyseProject(
            $target_project,
            $logger,
            $storage,
            $ignored_folders,
            $input->getOption(self::OPTION_TRACE[0])
        );

        $overwrite_files = !$input->getOption(self::OPTION_ANALYSE_ONLY[0]);
        $this->applyInstructions(
            $target_project,
            $modification_instructions,
            $input->getOption(self::OPTION_SHOW_DIFF[0]),
            $overwrite_files
        );

        $this->io->success('Done!');
        $this->printResults();
        $this->outputStatistics($stopwatch, $logger);
    }

    /**
     * Sets the storage type for the ProjectAnalyzer.
     * @param $storage_type
     * @param $db_config_location
     * @throws \InvalidArgumentException
     * @return RecordStorageInterface
     */
    private function getDataStorageType(string $storage_type, $db_config_location): RecordStorageInterface
    {
        if (!in_array($storage_type, self::STORAGE_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf("Invalid storage type '%s' provided.", $storage_type));
        }
        if ($storage_type === self::STORAGE_TYPE_DATABASE && $db_config_location === null) {
            throw new \InvalidArgumentException('No database config provided for database storage.');
        }

        if ($storage_type === self::STORAGE_TYPE_DATABASE) {
            return new DatabaseRecordStorage($db_config_location);
        }
        if ($storage_type === self::STORAGE_TYPE_FILE) {
            return new FileRecordStorage();
        }
        return new MemoryRecordStorage();
    }

    /**
     * In case a log directory has been set, a logger has to be created
     * with the output directory pointing to the given directory. Otherwise
     * a NullLogger is returned.
     *
     * @param string $log_dir
     * @return LoggerInterface
     * @throws \Exception
     */
    private function getLogger(string $log_dir = null): LoggerInterface
    {
        if (!$log_dir) {
            return new NullLogger();
        }

        $logger  = new Logger(self::NAME);
        $handler = new StreamHandler($log_dir);
        $handler->setFormatter(new ColoredLineFormatter());
        $logger->pushHandler($handler);
        $logger->pushProcessor(new PsrLogMessageProcessor());
        return $logger;
    }

    /**
     * Outputs the execution time in seconds and the peak memory used
     *  to both the console output and logger.
     *
     * @param Stopwatch $stopwatch
     * @param LoggerInterface $logger
     */
    private function outputStatistics(Stopwatch $stopwatch, LoggerInterface $logger)
    {
        $total_time = round($stopwatch->stop(self::NAME)->getDuration() / 1000, 2);
        $mem        = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $logger->info('DONE. Execution time: {time}s - Memory: {memory}MB', ['time' => $total_time,'memory' =>$mem]);
        $execution_statistics = sprintf('Execution time: %ss - Memory: %sMB', $total_time, $mem);
        $this->io->newLine();
        $this->io->note($execution_statistics);
    }

    /**
     * Outputs the diffs for each modification per file. The CodeEditor
     * passes a callable to all instructions, allowing the instruction to
     * output a diff before overwriting a file.
     */
    private function enableDiffOutput()
    {
        $this->io->section('Diffs');
        $differ = new Differ('', false);
        $this->io->writeln("--- Original\n+++ New\n");
        $this->code_editor->setDiffHandler(function (string $old, string $new, string $file) use ($differ) {
            $diff = $differ->diff($old, $new);

            if ($diff !== '') {
                $this->io->writeln($file);
                $this->io->writeln($diff);
            }
        });
    }

    /**
     * Starts analysing the target project using a dynamic- and static
     * analyzer.
     *
     * @param string $target_project
     * @param LoggerInterface $logger
     * @param RecordStorageInterface $storage
     * @param string[] $ignored_folders
     * @param string $trace
     * @return AbstractInstruction[]
     */
    private function analyseProject(
        string $target_project,
        LoggerInterface $logger,
        RecordStorageInterface $storage,
        array $ignored_folders,
        string $trace = null
    ): array {
        $this->io->text(sprintf('<info>Started analysing %s</info>', $target_project));

        $this->project_analyzer->setLogger($logger);
        $this->project_analyzer->addAnalyzer(new DynamicAnalyzer($storage, $ignored_folders, $logger, $trace));
        $this->project_analyzer->addAnalyzer(new StaticAnalyzer($ignored_folders, $logger));

        return $this->project_analyzer->analyse($target_project);
    }

    /**
     * Applies all generated instructions to the target project. If show-diff
     * is enabled, the diffs between the original and modified files are
     * outputted.
     *
     * @param string $target_project
     * @param AbstractInstruction[] $instructions
     * @param bool $show_diff
     * @param bool $overwrite_files
     */
    private function applyInstructions(
        string $target_project,
        array $instructions,
        bool $show_diff,
        bool $overwrite_files
    ) {
        if ($overwrite_files) {
            $this->io->text('<info>Applying generated instructions</info>');
        }
        if ($show_diff) {
            $this->enableDiffOutput();
        }

        $this->code_editor->setInstructions($instructions);
        $this->io->newLine();
        $this->code_editor->applyInstructions($target_project, $overwrite_files);
    }

    /**
     * Outputs a table containing the amount of data inferred.
     */
    private function printResults()
    {
        $this->io->section('Results');
        $this->io->text('Inferred and added the following amount of data:');

        $instructions = $this->code_editor->getAppliedInstructions();

        $inferred_data = [
            self::RESULTS_COLUMN_RETURN_TYPES => 0,
            self::RESULTS_COLUMN_TYPE_HINTS => 0,
            self::RESULTS_COLUMN_TOTAL => count($instructions)
        ];

        foreach ($instructions as $instruction) {
            if ($instruction instanceof ReturnTypeInstruction) {
                $inferred_data[self::RESULTS_COLUMN_RETURN_TYPES]++;
            }

            if ($instruction instanceof TypeHintInstruction) {
                $inferred_data[self::RESULTS_COLUMN_TYPE_HINTS]++;
            }
        }

        $this->io->table(array_keys($inferred_data), [array_values($inferred_data)]);
    }

    /**
     * @return string
     */
    public static function getExecutionId(): string
    {
        return self::$execution_id ?? self::$execution_id = uniqid('', false);
    }
}
