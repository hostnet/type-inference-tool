<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Tracer
 */
class TracerTest extends TestCase
{
    /**
     * @dataProvider exampleProjectDataProvider
     */
    public function testGenerateTraceShouldGenerateABootstrapAndTraces(string $project)
    {
        $fixtures       = dirname(__DIR__, 3) . '/Fixtures';
        $output_dir     = $fixtures . '/output/';
        $target_project = $fixtures . $project;
        $inferrer_dir   = dirname(__DIR__, 4);
        $tracer         = new Tracer($output_dir, $target_project, $inferrer_dir);
        $tracer->generateTrace();

        self::assertFileExists($output_dir . Tracer::OUTPUT_BOOTSTRAP_NAME . '.php');
        self::assertFileExists($tracer->getFullOutputTracePath());
    }

    public function exampleProjectDataProvider()
    {
        return [
            ['/ExampleDynamicAnalysis/Example-Project-1'],
            ['/ExampleDynamicAnalysis/Example-Project-2']
        ];
    }
}
