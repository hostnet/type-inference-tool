<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\UnresolvablePhpType;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedCall
 */
class AnalyzedCallTest extends TestCase
{
    public function testAnalyzedCallHasCorrectArguments()
    {
        $argument_types = [
            new NonScalarPhpType('Namespace', 'SomeObject', '', null, []),
            new UnresolvablePhpType(UnresolvablePhpType::INCONSISTENT),
            new UnresolvablePhpType(UnresolvablePhpType::NONE)
        ];
        $analyzed_call  = new AnalyzedCall($argument_types);

        self::assertSame($argument_types, $analyzed_call->getArguments());
    }
}
