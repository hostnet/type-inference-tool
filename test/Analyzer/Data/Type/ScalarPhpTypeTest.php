<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data\Type;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType
 */
class ScalarPhpTypeTest extends TestCase
{
    /**
     * @dataProvider correctScalarTypeNameDataProvider
     * @param string $type_name
     * @throws \InvalidArgumentException
     */
    public function testScalarPhpTypeIsCorrectType(string $type_name)
    {
        $php_type = new ScalarPhpType($type_name);
        self::assertSame($type_name, $php_type->getName());
        self::assertFalse($php_type->isNullable());

        $php_type->setNullable(true);
        self::assertTrue($php_type->isNullable());
    }

    public function testScalarPhpTypeThrowsErrorWhenIncorrectType()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ScalarPhpType('SomeInvalidScalarType');
    }

    public function correctScalarTypeNameDataProvider()
    {
        return [
            [ScalarPhpType::TYPE_INT],
            [ScalarPhpType::TYPE_FLOAT],
            [ScalarPhpType::TYPE_STRING],
            [ScalarPhpType::TYPE_BOOL],
        ];
    }
}
