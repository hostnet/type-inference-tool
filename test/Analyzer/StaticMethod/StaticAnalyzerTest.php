<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\StaticMethod\StaticAnalyzer
 */
class StaticAnalyzerTest extends TestCase
{
    public function testStaticAnalyzerGeneratesAnalyzedFunctions()
    {
        $analyzer = new StaticAnalyzer();
        $results  = $analyzer->collectAnalyzedFunctions('path/to/project');

        self::assertEmpty($results);
    }
}
