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
    public function testDynamicAnalyzerGeneratesAnalyzedFunctions()
    {
        $analyzer = new DynamicAnalyzer();
        $result   = $analyzer->collectAnalyzedFunctions('path\\to\\project');

        self::assertEmpty($result);
    }
}
