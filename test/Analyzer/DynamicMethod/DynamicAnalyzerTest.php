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
         $this->example_project_dir = dirname(__DIR__, 2) . '/Fixtures/ExampleDynamicAnalysis/Example-Project-1';
    }

    public function testDynamicAnalyzerGeneratesAnalyzedFunctions()
    {
        $results = $this->analyzer->collectAnalyzedFunctions($this->example_project_dir);
        self::assertCount(28, $results);
    }
}
