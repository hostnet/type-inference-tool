<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass
 */
class AnalyzedClassTest extends TestCase
{
    public function testAnalyzedClassContainsCorrectData()
    {
        $namespace  = 'Some\\Namespace';
        $class_name = 'SomeClass';
        $file_path  = 'some/path/to/the/file.php';
        $methods    = ['fn1', 'fn2'];
        $class      = new AnalyzedClass($namespace, $class_name, $file_path, null, [], $methods);

        self::assertSame($namespace, $class->getNamespace());
        self::assertSame($class_name, $class->getClassName());
        self::assertSame($file_path, $class->getFullPath());
        self::assertSame($namespace . '\\' . $class_name, $class->getFqcn());
        self::assertSame($methods, $class->getMethods());
    }

    public function testRetrieveAllParentTypes()
    {
        $namespace  = 'Just\\Some\\Namespace';
        $extends_1  = new AnalyzedClass($namespace, 'AbstractClass1', '', null, [], []);
        $extends_2  = new AnalyzedClass($namespace, 'AbstractClass2', '', null, [], []);
        $interface  = new AnalyzedClass($namespace, 'ClassInterface4', '', $extends_1, [], []);
        $implements = [
            new AnalyzedClass($namespace, 'ClassInterface1', '', null, [], []),
            new AnalyzedClass($namespace, 'ClassInterface2', '', $extends_2, [], []),
            new AnalyzedClass($namespace, 'ClassInterface3', '', null, [$interface], [])
        ];

        $analyzed_class = new AnalyzedClass($namespace, 'MainClass', '', $extends_1, $implements, []);
        $parent_classes = $analyzed_class->getParents();

        self::assertCount(7, $parent_classes);
        self::assertContains($analyzed_class, $parent_classes);
        self::assertContains($extends_1, $parent_classes);
        self::assertContains($implements[0], $parent_classes);
        self::assertContains($implements[1], $parent_classes);
        self::assertContains($extends_2, $parent_classes);
        self::assertContains($implements[2], $parent_classes);
        self::assertContains($interface, $parent_classes);
    }

    public function testAnalyzedClassHasCorrectFullyQualifiedName()
    {
        $namespace  = 'Just\\Some\\Namespace';
        $class_name = 'ClassName';
        $class      = new AnalyzedClass($namespace, $class_name, '', null, [], []);

        self::assertSame($namespace . '\\' . $class_name, $class->getFqcn());
        self::assertSame($namespace, $class->getNamespace());
        self::assertSame($class_name, $class->getClassName());
    }

    public function testGetMatchingAnalyzedClassesFromTwoLists()
    {
        $non_matching_class_1 = new AnalyzedClass('Namespace', 'Class1', '', null, []);
        $non_matching_class_2 = new AnalyzedClass('Namespace', 'Class2', '', null, []);
        $matching_class_1     = new AnalyzedClass('Namespace', 'Class3', '', null, []);
        $matching_class_2     = new AnalyzedClass('Namespace', 'Class4', '', null, []);

        $matches = AnalyzedClass::matchAnalyzedClasses(
            [$matching_class_1, $matching_class_2, $non_matching_class_1],
            [$matching_class_1, $matching_class_2, $non_matching_class_2]
        );

        self::assertCount(2, $matches);
        self::assertContains($matching_class_1, $matches);
        self::assertContains($matching_class_2, $matches);
    }
}
