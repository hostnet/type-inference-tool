<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Tool;

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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
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

    const RESULTS_COLUMN_RETURN_TYPES = 'Return types';
    const RESULTS_COLUMN_TYPE_HINTS   = 'Type hints';
    const RESULTS_COLUMN_TOTAL        = 'Total';

    /**
     * @var SymfonyStyle
     */
    private $io;

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
                'Execute analysis without modifying the target projects files'
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
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Type-Inference-Tool');

        $log_dir = $input->getOption(self::OPTION_LOG_DIR[0]);
        $logger  = null;
        if ($log_dir) {
            $logger = new Logger('type-inference-tool');
            $logger->pushHandler(new StreamHandler($log_dir));
            $logger->pushProcessor(new PsrLogMessageProcessor());
        }

        $target_project            = $input->getArgument(self::ARG_TARGET);
        $modification_instructions = $this->analyseProject($target_project, $logger);

        if (!$input->getOption(self::OPTION_ANALYSE_ONLY[0])) {
            $this->applyInstructions($target_project, $modification_instructions);
        }

        $this->io->text('<info>Done!</info>');
        $this->printResults($modification_instructions);
    }

    /**
     * @param string $target_project
     * @param LoggerInterface $logger
     * @return AbstractInstruction[]
     * @throws \InvalidArgumentException
     */
    private function analyseProject(string $target_project, LoggerInterface $logger = null)
    {
        $this->io->text(sprintf('<info>Started analysing %s</info>', $target_project));

        $project_analyzer = new ProjectAnalyzer($logger);
        $project_analyzer->addAnalyzer(new DynamicAnalyzer());
        $project_analyzer->addAnalyzer(new StaticAnalyzer());

        return $project_analyzer->analyse($target_project);
    }

    /**
     * @param string $target_project
     * @param AbstractInstruction[] $instructions
     */
    private function applyInstructions(string $target_project, array $instructions)
    {
        $this->io->text('<info>Applying generated instructions</info>');
        $code_editor = new CodeEditor($target_project);
        $code_editor->setInstructions($instructions);
        $code_editor->applyInstructions();
    }

    /**
     * @param AbstractInstruction[] $instructions
     */
    private function printResults(array $instructions)
    {
        $this->io->section('Results');
        $this->io->text('Inferred the following amount of data:');

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
