<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedCall
 */
class AnalyzedCallTest extends TestCase
{
    public function testAnalyzedCallHasCorrectArguments()
    {
        $argument_types = [new PhpType('SomeObject'), new PhpType(PhpType::INCONSISTENT), new PhpType(PhpType::NONE)];
        $analyzed_call  = new AnalyzedCall($argument_types);

        self::assertSame($argument_types, $analyzed_call->getArguments());
    }
}
