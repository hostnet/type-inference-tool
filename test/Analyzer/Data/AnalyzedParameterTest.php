<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Mapper\TracerPhpTypeMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedParameter
 */
class AnalyzedParameterTest extends TestCase
{
    public function testWhenCreatingAnalyzedParameterItShouldHaveCorrectProperties()
    {
        $analyzed_parameter = new AnalyzedParameter();

        self::assertSame(TracerPhpTypeMapper::TYPE_UNKNOWN, $analyzed_parameter->getType());
        self::assertFalse($analyzed_parameter->hasTypeHint());
        self::assertFalse($analyzed_parameter->hasDefaultValue());
        self::assertEmpty($analyzed_parameter->getName());

        $parameter_name = 'parameter_name';
        $analyzed_parameter->setName($parameter_name);
        $analyzed_parameter->setHasDefaultValue(true);
        $analyzed_parameter->setHasTypeHint(true);
        $analyzed_parameter->setType(ScalarPhpType::TYPE_INT);

        self::assertSame(ScalarPhpType::TYPE_INT, $analyzed_parameter->getType());
        self::assertTrue($analyzed_parameter->hasTypeHint());
        self::assertTrue($analyzed_parameter->hasDefaultValue());
        self::assertSame($parameter_name, $analyzed_parameter->getName());
    }
}
