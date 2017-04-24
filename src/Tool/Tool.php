<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Tool;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\DynamicAnalyzer;
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
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class used to start the tool. Applies dynamic- and static analysis to infer
 * parameter- and return types and add them as either a type hint or return type
 * declaration.
 */
class Tool extends Command
{
    const EXECUTE_COMMAND     = 'execute';
    const ARG_TARGET          = 'target';
    const OPTION_LOG_DIR      = ['log-dir', 'l'];
    const OPTION_ANALYSE_ONLY = ['analyse-only', 'a'];
    const OPTION_SHOW_DIFF    = ['show-diff', 'd'];

    const RESULTS_COLUMN_RETURN_TYPES = 'Return types';
    const RESULTS_COLUMN_TYPE_HINTS   = 'Type hints';
    const RESULTS_COLUMN_TOTAL        = 'Total';

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
     * @inheritdoc
     *
     * @param ProjectAnalyzer $project_analyzer
     * @throws LogicException
     */
    public function __construct(ProjectAnalyzer $project_analyzer, CodeEditor $code_editor, string $name = null)
    {
        parent::__construct($name);

        $this->project_analyzer = $project_analyzer;
        $this->code_editor      = $code_editor;
    }

    /**
     * @inheritdoc
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
            );
    }

    /**
     * @inheritdoc
     *
     * @throws InvalidArgumentException
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start_time = microtime(true);
        $this->io   = new SymfonyStyle($input, $output);
        $this->io->title('Type-Inference-Tool');

        $target_project = $input->getArgument(self::ARG_TARGET);
        $logger         = $this->getLogger($input->getOption(self::OPTION_LOG_DIR[0]));
        $logger->info('Type-Inference-Tool started for ' . $target_project);

        $modification_instructions = $this->analyseProject($target_project, $logger);

        $this->applyInstructions(
            $target_project,
            $modification_instructions,
            $input->getOption(self::OPTION_SHOW_DIFF[0]),
            !$input->getOption(self::OPTION_ANALYSE_ONLY[0])
        );

        $this->io->success('Done!');
        $this->printResults();
        $this->outputStatistics($start_time, $logger);
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
    private function getLogger(string $log_dir = null)
    {
        if (!$log_dir) {
            return new NullLogger();
        }

        $logger  = new Logger('type-inference-tool');
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
     * @param float $start_time
     * @param LoggerInterface $logger
     */
    private function outputStatistics(float $start_time, LoggerInterface $logger)
    {
        $total_time = round(microtime(true) - $start_time, 2);
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
    private function showDiffs()
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
     * @return AbstractInstruction[]
     * @throws \InvalidArgumentException
     */
    private function analyseProject(string $target_project, LoggerInterface $logger = null)
    {
        $this->io->text(sprintf('<info>Started analysing %s</info>', $target_project));

        if ($logger !== null) {
            $this->project_analyzer->setLogger($logger);
        }
        $this->project_analyzer->addAnalyzer(new DynamicAnalyzer($logger));
        $this->project_analyzer->addAnalyzer(new StaticAnalyzer());

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
            $this->showDiffs();
        }
        $this->code_editor->setInstructions($instructions);
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
}
