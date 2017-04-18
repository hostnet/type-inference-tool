<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedReturn
 */
class AnalyzedReturnTest extends TestCase
{
    public function testAnalyzedReturnHasCorrectReturnType()
    {
        $type            = 'SomeObject';
        $php_type        = new PhpType($type);
        $analyzed_return = new AnalyzedReturn($php_type);

        self::assertSame($php_type, $analyzed_return->getType());
        self::assertSame($type, $analyzed_return->getType()->getName());
    }
}
