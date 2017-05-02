<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Bootstrap;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Bootstrap\BootstrapGenerator
 */
class BootstrapGeneratorTest extends TestCase
{
    /**
     * @var Filesystem
     */
    private $file_system;

    /**
     * @var string
     */
    private $fixtures;

    /**
     * @var string
     */
    private $output_dir;

    protected function setUp()
    {
        $this->file_system = new Filesystem();
        $this->fixtures    = dirname(__DIR__, 3) . '/Fixtures';
        $this->output_dir  = __dir__ . '/output/';
        $this->file_system->remove($this->output_dir);
    }

    protected function tearDown()
    {
        $this->file_system->remove($this->output_dir);
    }

    /**
     * @dataProvider projectLocationDataProvider
     * @param string $project
     */
    public function testGenerateBootstrapShouldOutputTargetProjectBootstrap(string $project)
    {
        $project_dir = $this->fixtures . $project;
        $output_file = 'generated_bootstrap';

        $bootstrap_generator = new BootstrapGenerator();
        $bootstrap_generator->generateBootstrap($project_dir, $this->output_dir, $output_file);

        $outputted_file = $this->output_dir . $output_file . '.php';
        self::assertFileExists($outputted_file);

        $expected = sprintf(
            <<<'PHP'
<?php

xdebug_start_trace('%s', 2);

require_once '%s/vendor/autoload.php';
PHP
            ,
            $this->output_dir . BootstrapGenerator::TRACE_FILE_NAME,
            $project_dir
        );
        self::assertStringEqualsFile($outputted_file, $expected);
    }

    public function projectLocationDataProvider()
    {
        return [
            ['/ExampleDynamicAnalysis/Example-Project-1'],
            ['/ExampleDynamicAnalysis/Example-Project-2']
        ];
    }
}
