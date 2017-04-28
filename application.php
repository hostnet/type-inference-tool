<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
require __DIR__ . '/vendor/autoload.php';

use Hostnet\Component\TypeInference\Analyzer\ProjectAnalyzer;
use Hostnet\Component\TypeInference\CodeEditor\CodeEditor;
use Hostnet\Component\TypeInference\Tool\Tool;
use Symfony\Component\Console\Application;

// TODO - Might refactor to single command application:
// (http://symfony.com/doc/current/components/console/single_command_tool.html)

$application  = new Application(Tool::NAME);
$tool_command = new Tool(new ProjectAnalyzer(), new CodeEditor());

$application->setDefaultCommand($tool_command->getName());
$application->add($tool_command);

$application->run();
