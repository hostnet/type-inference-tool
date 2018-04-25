<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data\Type;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType
 */
class NonScalarPhpTypeTest extends TestCase
{
    private $namespace  = 'Some\\Namespace';
    private $class_name = 'SomeClass';

    public function testNonScalarTypeItsNameIsFqcn()
    {
        $php_type = new NonScalarPhpType($this->namespace, $this->class_name, '', null, []);

        self::assertSame($this->namespace . '\\' . $this->class_name, $php_type->getName());
        self::assertFalse($php_type->isNullable());

        $php_type->setNullable(true);
        self::assertTrue($php_type->isNullable());
    }

    public function testAnalyzedClassToNonScalarTypeContainSameValues()
    {
        $extends        = new AnalyzedClass($this->namespace, 'AbstractClass', '', null, [], []);
        $implements     = [new AnalyzedClass($this->namespace, 'SomeInterface', '', null, [], [])];
        $methods        = ['fn1', 'fn2'];
        $analyzed_class = new AnalyzedClass($this->namespace, $this->class_name, '', $extends, $implements, $methods);
        $php_type       = NonScalarPhpType::fromAnalyzedClass($analyzed_class);

        self::assertInstanceOf(NonScalarPhpType::class, $php_type);
        self::assertSame($analyzed_class->getFqcn(), $php_type->getName());
    }

    public function testGetCommonParentShouldReturnTheCommonParentOfMultipleClasses()
    {
        $parent    = new AnalyzedClass('Ns', 'AbstractClass', '', null, []);
        $interface = new AnalyzedClass('Ns', 'SomeInterface', '', $parent, []);
        $class_a   = new NonScalarPhpType('Ns', 'ClassA', '', $parent, []);
        $class_b   = new NonScalarPhpType('Ns', 'ClassB', '', null, [$interface]);
        $class_c   = new NonScalarPhpType(
            'Ns',
            'ClassC',
            '',
            new AnalyzedClass('Ns', 'Someclass', '', new AnalyzedClass('Ns', 'AnotherSomeClass', '', $class_b, []), []),
            []
        );

        $return_types  = [$class_a, $class_b, $class_c];
        $common_parent = NonScalarPhpType::getCommonParent($return_types);

        self::assertNotInstanceOf(UnresolvablePhpType::class, $common_parent);
        self::assertSame($common_parent->getName(), $parent->getFqcn());
    }

    public function testGetCommonParentShouldByUnresolvedWhenNoCommonParent()
    {
        $return_types  = [
            new AnalyzedClass('Namespace', 'ClassA', '', null, []),
            new AnalyzedClass('Namespace', 'ClassB', '', null, []),
        ];
        $common_parent = NonScalarPhpType::getCommonParent($return_types);

        self::assertInstanceOf(UnresolvablePhpType::class, $common_parent);
        self::assertSame(UnresolvablePhpType::INCONSISTENT, $common_parent->getName());
    }

    public function testGetCommonParentShouldBeUnresolvedWhenTypesContainUnresolvable()
    {
        $return_types = [
            new AnalyzedClass('Namespace', 'SomeClass', '', null, []),
            new UnresolvablePhpType(UnresolvablePhpType::NONE),
        ];

        self::assertInstanceOf(UnresolvablePhpType::class, NonScalarPhpType::getCommonParent($return_types));
    }

    public function testGetCommonParentShouldBeUnresolvedWhenContainingScalarAndNonScalarTypes()
    {
        $types = [
            new NonScalarPhpType(null, 'array', null, null, [], []),
            new ScalarPhpType(ScalarPhpType::TYPE_STRING),
            new ScalarPhpType(ScalarPhpType::TYPE_STRING),
            new ScalarPhpType(ScalarPhpType::TYPE_STRING),
        ];

        self::assertInstanceOf(UnresolvablePhpType::class, NonScalarPhpType::getCommonParent($types));
    }
}
