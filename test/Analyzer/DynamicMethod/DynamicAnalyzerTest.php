<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\DynamicAnalyzer
 */
class DynamicAnalyzerTest extends TestCase
{
    /**
     * @var DynamicAnalyzer
     */
    private $analyzer;

    /**
     * @var string
     */
    private $example_project_dir;

    protected function setUp()
    {
        $this->analyzer            = new DynamicAnalyzer();
        $this->example_project_dir = dirname(__DIR__, 2) . '/Fixtures/ExampleDynamicAnalysis/Example-Project';
    }

    // TODO - TearDown: remove output files (Xdebug trace + generated bootstrap)

    public function testDynamicAnalyzerGeneratesAnalyzedFunctions()
    {
        self::markTestSkipped('TODO');
        $result = $this->analyzer->collectAnalyzedFunctions($this->example_project_dir);
//
//        self::assertEmpty($result);
        self::assertTrue(true);
    }
}
