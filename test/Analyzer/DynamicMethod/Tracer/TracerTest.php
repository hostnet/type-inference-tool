<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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

    /**
     * @var string
     */
    private $bootstrap_path;

    /**
     * @var string
     */
    private $trace_path;

    protected function setUp()
    {
        $this->output_directory = __DIR__ . '/output/';
        $this->file_system      = new Filesystem();
    }

    protected function tearDown()
    {
        $this->file_system->remove($this->trace_path);
        $this->file_system->remove($this->bootstrap_path);
    }

    public function testGenerateTraceShouldGenerateABootstrapAndTraces()
    {
        $project        = '/ExampleDynamicAnalysis/Example-Project-1';
        $fixtures       = dirname(__DIR__, 3) . '/Fixtures';
        $target_project = $fixtures . $project;
        $inferrer_dir   = dirname(__DIR__, 4);
        $tracer         = new Tracer($this->output_directory, $target_project, $inferrer_dir, new NullLogger());
        $tracer->generateTrace();


        $this->trace_path     = $tracer->getFullOutputTracePath();
        $this->bootstrap_path = $tracer->getFullOutputBootstrapPath();

        self::assertFileExists($this->bootstrap_path);
        self::assertFileExists($this->trace_path);
    }
}
