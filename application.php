<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
require __DIR__ . '/vendor/autoload.php';

use Hostnet\Component\TypeInference\Tool\Tool;
use Symfony\Component\Console\Application;

$application  = new Application('Type-inference-tool');
$tool_command = new Tool();

$application->setDefaultCommand($tool_command->getName());
$application->add(new Tool());

$application->run();
