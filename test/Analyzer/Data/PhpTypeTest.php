<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\PhpType
 */
class PhpTypeTest extends TestCase
{
    public function testPhpTypeHasCorrectValue()
    {
        $type     = 'SomeObject';
        $php_type = new PhpType($type);

        self::assertSame($type, $php_type->getName());
    }
}
