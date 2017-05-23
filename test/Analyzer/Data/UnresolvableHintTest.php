<?php
declare(strict_types=1);
/**
 * @copyright 2017 Hostnet B.V.
 */

namespace Hostnet\Component\TypeInference\Analyzer\Data;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\UnresolvableHint
 */
class UnresolvableHintTest extends TestCase
{
    public function testCreatedUnresolvableHintShouldHoldTheCorrectProperties()
    {
        $function_name     = 'foobar';
        $class             = new AnalyzedClass('Namespace', 'SomeClass', 'File.php', null, [], [$function_name]);
        $unresolvable_hint = new UnresolvableHint($class, $function_name, UnresolvableHint::HINT_TYPE_RETURN);

        self::assertSame($function_name, $unresolvable_hint->getFunctionName());
        self::assertSame($class, $unresolvable_hint->getClass());
        self::assertSame(UnresolvableHint::HINT_TYPE_RETURN, $unresolvable_hint->getHintType());
        self::assertSame(UnresolvableHint::HINT_TYPE_UNDEFINED, $unresolvable_hint->getArgumentNumber());
    }
}
