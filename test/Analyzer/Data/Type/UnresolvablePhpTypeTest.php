<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data\Type;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\Type\UnresolvablePhpType
 */
class UnresolvablePhpTypeTest extends TestCase
{
    /**
     * @dataProvider correctUnresolvableTypeNameDataProvider
     * @param string $type_name
     * @throws \InvalidArgumentException
     */
    public function testUnresolvablePhpTypeIsCorrectType(string $type_name)
    {
        $php_type = new UnresolvablePhpType($type_name);
        self::assertSame($type_name, $php_type->getName());
    }

    public function testUnresolvablePhpTypeThrowsErrorWhenIncorrectType()
    {
        $this->expectException(\InvalidArgumentException::class);
        new UnresolvablePhpType('SomeInvalidType');
    }

    public function correctUnresolvableTypeNameDataProvider()
    {
        return [
            [UnresolvablePhpType::INCONSISTENT],
            [UnresolvablePhpType::NONE],
        ];
    }
}
