<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

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
     * @param string $message
     */
    public function testUnresolvablePhpTypeIsCorrectType(string $type_name, string $message)
    {
        $php_type = new UnresolvablePhpType($type_name, $message);
        self::assertContains($type_name, $php_type->getName());
        self::assertContains($message, $php_type->getName());
        self::assertSame($type_name, $php_type->getType());
        self::assertFalse($php_type->isNullable());

        $php_type->setNullable(true);
        self::assertTrue($php_type->isNullable());
    }

    public function testUnresolvablePhpTypeThrowsErrorWhenIncorrectType()
    {
        $this->expectException(\InvalidArgumentException::class);
        new UnresolvablePhpType('SomeInvalidType');
    }

    /**
     * @dataProvider unresolvablePhpTypeDataProvider
     * @param PhpTypeInterface $type
     * @param bool $is_nullable
     */
    public function testWhenUnresolvablePhpTypeHasTypeNoneThenItIsNullable(PhpTypeInterface $type, bool $is_nullable)
    {
        self::assertSame($is_nullable, UnresolvablePhpType::isPhpTypeNullable($type));
    }

    public function correctUnresolvableTypeNameDataProvider(): array
    {
        return [
            [UnresolvablePhpType::INCONSISTENT, 'message'],
            [UnresolvablePhpType::NONE, 'message'],
        ];
    }

    public function unresolvablePhpTypeDataProvider(): array
    {
        return [
            [new UnresolvablePhpType(UnresolvablePhpType::INCONSISTENT), false],
            [new UnresolvablePhpType(UnresolvablePhpType::NONE), true],
            [new UnresolvablePhpType(UnresolvablePhpType::DOCBLOCK_MULTIPLE), false],
            [new UnresolvablePhpType(UnresolvablePhpType::DOCBLOCK), false],
        ];
    }
}
