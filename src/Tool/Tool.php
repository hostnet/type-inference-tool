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
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * TODO: This class is going to be replaced for a Symfony Console Application class
 *
 * Class used to start the tool. Applies dynamic- and static analysis to infer
 * parameter- and return types and add them as either a type hint or return type
 * declaration.
 */
class Tool
{
    /**
     * @var ProjectAnalyzer
     */
    private $project_analyzer;

    /**
     * @var CodeEditor
     */
    private $code_editor;

    /**
     * @param string $target_project
     * @param string $log_output_dir
     * @throws \Exception
     */
    public function __construct(string $target_project, string $log_output_dir)
    {
        $logger = new Logger('type-inference-tool');
        $logger->pushHandler(new StreamHandler($log_output_dir));
        $logger->pushProcessor(new PsrLogMessageProcessor());

        $this->project_analyzer = new ProjectAnalyzer($target_project, $logger);
        $this->code_editor      = new CodeEditor($target_project);
    }

    /**
     * Start executing the analyzing process to infer parameter- and return types.
     * Then determines what return type declarations and type hints can be added,
     * and updates the files.
     *
     * @param bool $update_files Whether the files should be updated
     */
    public function execute(bool $update_files = true)
    {
        $this->project_analyzer->addAnalyzer(new DynamicAnalyzer());
        $this->project_analyzer->addAnalyzer(new StaticAnalyzer());

        $modification_instructions = $this->project_analyzer->analyse();

        if (!$update_files) {
            return;
        }

        $this->code_editor->setInstructions($modification_instructions);
        $this->code_editor->applyInstructions();
    }

    /**
     * @param ProjectAnalyzer $project_analyzer
     */
    public function setProjectAnalyzer(ProjectAnalyzer $project_analyzer)
    {
        $this->project_analyzer = $project_analyzer;
    }

    /**
     * @param CodeEditor $code_editor
     */
    public function setCodeEditor(CodeEditor $code_editor)
    {
        $this->code_editor = $code_editor;
    }
}
