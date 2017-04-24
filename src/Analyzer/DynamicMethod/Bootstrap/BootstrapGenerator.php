<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Bootstrap;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class used the create a bootstrap file for a given target project.
 * This bootstrap allows for Xdebug trace generation
 */
class BootstrapGenerator
{
    const TRACE_FILE_NAME = 'trace_results';

    /**
     * @var Filesystem
     */
    private $fs;

    public function __construct()
    {
        $this->fs = new Filesystem();
    }

    /**
     * Generates and saves a bootstrap file that enables tracing to the output directory.
     * The target project its actual bootstrap file is included.
     *
     * @param string $target_project_directory
     * @param string $output_dir
     * @param string $output_file
     * @throws IOException
     */
    public function generateBootstrap(string $target_project_directory, string $output_dir, string $output_file)
    {
        $bootstrap = <<<'PHP'
<?php

xdebug_start_trace('%s', 2);

require_once '%s%s';
PHP;

        $contents = sprintf(
            $bootstrap,
            $output_dir . self::TRACE_FILE_NAME,
            $target_project_directory,
            $this->retrieveBootstrapLocation($target_project_directory)
        );

        if (!$this->fs->exists($output_dir)) {
            $this->fs->mkdir($output_dir);
        }

        $this->fs->dumpFile($output_dir . $output_file . '.php', $contents);
    }

    /**
     * Returns the location of the bootstrap file of the target project.
     *
     * @param string $target_project_directory
     * @return string
     */
    private function retrieveBootstrapLocation(string $target_project_directory): string
    {
        $default_bootstrap_location = '/vendor/autoload.php';
        if (!$this->fs->exists($target_project_directory . 'phpunit.xml.dist' /* TODO - Could also be phpunit.xml */)) {
            return $default_bootstrap_location;
        }

        $phpunit_config = file_get_contents($target_project_directory . 'phpunit.xml.dist');
        $document       = new \DOMDocument();
        $document->loadXML($phpunit_config);
        $bootstrap_location = $document->getElementsByTagName('phpunit')->item(0)->getAttribute('bootstrap');

        return $bootstrap_location;
    }
}
