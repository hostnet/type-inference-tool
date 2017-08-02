<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Bootstrap\BootstrapGenerator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Retrieves execution traces by applying Xdebug to the execution of
 * PHPUnit tests of a project.
 */
class Tracer
{
    const LOG_PREFIX            = 'TRACER: ';
    const DEFAULT_TEST_FOLDER   = 'test';
    const OUTPUT_FOLDER_NAME    = '/output/';
    const OUTPUT_BOOTSTRAP_NAME = 'generated_autoload';

    private $settings = [
        'xdebug.collect_params' => '1',
        'xdebug.collect_return' => '1',
        'xdebug.collect_includes' => '0',
        'xdebug.coverage_enable' => '0',
        'xdebug.extended_info' => '0',
    ];

    /**
     * @var string
     */
    private $target_project_directory;

    /**
     * @var string
     */
    private $output_directory;

    /**
     * @var string
     */
    private $test_folder;

    /**
     * @var string
     */
    private $inferrer_directory;

    /**
     * @var string
     */
    private $output_trace_name;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param string $output_dir
     * @param string $target_project_directory
     * @param string $inferrer_directory
     * @param LoggerInterface $logger
     * @param string $test_folder
     */
    public function __construct(
        string $output_dir,
        string $target_project_directory,
        string $inferrer_directory,
        LoggerInterface $logger,
        string $test_folder = self::DEFAULT_TEST_FOLDER
    ) {
        $this->output_directory         = $output_dir;
        $this->target_project_directory = $target_project_directory;
        $this->inferrer_directory       = $inferrer_directory;
        $this->test_folder              = '/' . $test_folder;
        $this->output_trace_name        = uniqid(BootstrapGenerator::TRACE_FILE_NAME, false);
        $this->output_bootstrap_name    = uniqid(self::OUTPUT_BOOTSTRAP_NAME, false);
        $this->logger                   = $logger;
    }

    /**
     * Generates a bootstrap file, executes PHPUnit for the target project and stores
     * the generated trace file to the output directory.
     *
     * @throws IOException
     */
    public function generateTrace()
    {
        $this->createBootstrapFile($this->output_directory);
        $this->logger->debug(self::LOG_PREFIX . 'Started running target project unit tests...');
        exec($this->getExecuteCommand());
        $this->logger->debug(self::LOG_PREFIX . 'Unit tests finished and trace generated');
    }

    /**
     * Creates a bootstrap file used by PHPUnit. This bootstrap file contains a command
     * to start tracing.
     *
     * @param string $output_directory
     * @throws IOException
     */
    private function createBootstrapFile(string $output_directory)
    {
        $bootstrap_generator = new BootstrapGenerator();
        $bootstrap_generator->generateBootstrap(
            $this->target_project_directory,
            $output_directory,
            $this->output_bootstrap_name,
            $this->output_trace_name
        );
    }

    /**
     * @return string Command-line key - value entries (d-flags)
     */
    private function getSettings(): string
    {
        $options = '';

        foreach ($this->settings as $setting => $value) {
            $options .= sprintf('-d %s=%s ', $setting, $value);
        }

        return $options;
    }

    /**
     * Returns the command to execute PHPUnit with the generated bootstrap for the
     * target project.
     *
     * @return string
     */
    private function getExecuteCommand(): string
    {
        $php_unit_folder = '/vendor/phpunit/phpunit/phpunit';
        $project_dir     = $this->inferrer_directory;

        if (file_exists($this->target_project_directory . $php_unit_folder)) {
            $project_dir = $this->target_project_directory;
        }

        return sprintf(
            '%s --bootstrap %s %s%s %s',
            $project_dir . $php_unit_folder,
            $this->getFullOutputBootstrapPath(),
            $this->target_project_directory,
            $this->test_folder,
            $this->getSettings()
        );
    }

    /**
     * @return string
     */
    public function getFullOutputTracePath(): string
    {
        return $this->output_directory . $this->output_trace_name . '.xt';
    }

    /**
     * @return string
     */
    public function getFullOutputBootstrapPath(): string
    {
        return $this->output_directory . $this->output_bootstrap_name . '.php';
    }
}
