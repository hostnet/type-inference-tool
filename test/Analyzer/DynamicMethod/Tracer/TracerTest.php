<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Tracer
 */
class TracerTest extends TestCase
{
    /**
     * @var string
     */
    private $output_directory;

    /**
     * @var Filesystem
     */
    private $file_system;

    protected function setUp()
    {
        $this->output_directory = __DIR__ . '/output/';
        $this->file_system      = new Filesystem();
        $this->file_system->remove($this->output_directory);
    }

    protected function tearDown()
    {
        $this->file_system->remove($this->output_directory);
    }

    public function testGenerateTraceShouldGenerateABootstrapAndTraces()
    {
        $project        = '/ExampleDynamicAnalysis/Example-Project-1';
        $fixtures       = dirname(__DIR__, 3) . '/Fixtures';
        $target_project = $fixtures . $project;
        $inferrer_dir   = dirname(__DIR__, 4);
        $tracer         = new Tracer($this->output_directory, $target_project, $inferrer_dir);
        $tracer->generateTrace();

        self::assertFileExists($this->output_directory . Tracer::OUTPUT_BOOTSTRAP_NAME . '.php');
        self::assertFileExists($tracer->getFullOutputTracePath());
    }
}
