<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedReturn
 */
class AnalyzedReturnTest extends TestCase
{
    public function testAnalyzedReturnHasCorrectReturnType()
    {
        $php_type        = new NonScalarPhpType('ns', 'SomeObject', '', null, []);
        $analyzed_return = new AnalyzedReturn($php_type);

        self::assertSame($php_type, $analyzed_return->getType());
        self::assertSame($php_type->getName(), $analyzed_return->getType()->getName());
    }

    public function testRemoveAnalyzedReturnsDuplicatesRemovesDuplicates()
    {
        $analyzed_returns = [
            new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_STRING)),
            new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_STRING)),
            new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_INT)),
        ];

        $filtered_returns = AnalyzedReturn::removeAnalyzedReturnsDuplicates($analyzed_returns);

        self::assertCount(2, $filtered_returns);
        self::assertSame(ScalarPhpType::TYPE_STRING, $filtered_returns[0]->getType()->getName());
        self::assertSame(ScalarPhpType::TYPE_INT, $filtered_returns[1]->getType()->getName());
    }
}
