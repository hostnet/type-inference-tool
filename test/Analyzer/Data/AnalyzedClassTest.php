<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass
 */
class AnalyzedClassTest extends TestCase
{
    /**
     * @var string
     */
    private $namespace = 'Some\\Namespace';

    /**
     * @var string
     */
    private $class_name = 'SomeClass';

    /**
     * @var string
     */
    private $file_path = 'some/path/to/the/file.php';

    /**
     * @var string[]
     */
    private $methods = ['fn1', 'fn2'];

    /**
     * @var AnalyzedClass
     */
    private $example_class;

    protected function setUp()
    {
        $this->example_class = new AnalyzedClass(
            $this->namespace,
            $this->class_name,
            $this->file_path,
            null,
            [],
            $this->methods
        );
    }

    public function testAnalyzedClassContainsCorrectData()
    {
        self::assertSame($this->namespace, $this->example_class->getNamespace());
        self::assertSame($this->class_name, $this->example_class->getClassName());
        self::assertSame($this->file_path, $this->example_class->getFullPath());
        self::assertSame($this->namespace . '\\' . $this->class_name, $this->example_class->getFqcn());
        self::assertSame($this->methods, $this->example_class->getMethods());
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

    public function testAddMethodShouldAppendNonExistingMethods()
    {
        $function_1 = 'Function1';
        $function_2 = 'Function2';
        $class      = new AnalyzedClass('Namespace', 'Classname', 'file.php', null, []);

        $class->addMethod($function_1);
        $class->addMethod($function_1);
        $class->addMethod($function_1);
        $class->addMethod($function_2);
        $class->addMethod($function_2);
        $class->addMethod($function_2);

        $methods = $class->getMethods();
        self::assertCount(2, $methods);
        self::assertContains($function_1, $methods);
        self::assertContains($function_2, $methods);
    }

    public function testComposeAnalyzedClassBySettersShouldHaveCorrectValues()
    {
        $extended_class    = new AnalyzedClass('Namespace', 'AbstractClass', '/file0.php');
        $implemented_class = new AnalyzedClass('Namespace', 'ClassInterface', '/file1.php');

        $analyzed_class = new AnalyzedClass();
        $analyzed_class->setNamespace($this->namespace);
        $analyzed_class->setClassName($this->class_name);
        $analyzed_class->setFullPath($this->file_path);
        $analyzed_class->setExtends($extended_class);
        $analyzed_class->setImplements([$implemented_class]);

        $expected = new AnalyzedClass(
            $this->namespace,
            $this->class_name,
            $this->file_path,
            $extended_class,
            [$implemented_class]
        );

        self::assertEquals($expected, $analyzed_class);
    }

    public function testAddImplementedClassShouldOverwriteExistingImplementedClass()
    {
        $analyzed_class = new AnalyzedClass('Namespace', 'ClassName', 'File.php', null, [], []);
        $analyzed_class->addImplementedClass(new AnalyzedClass('Namespace', 'ClassInterface'));

        self::assertCount(1, $analyzed_class->getImplements());
        self::assertNull($analyzed_class->getImplements()[0]->getFullPath());

        $analyzed_class->addImplementedClass(new AnalyzedClass('Namespace', 'ClassInterface', 'file1.php'));

        self::assertCount(1, $analyzed_class->getImplements());
        self::assertNotNull($analyzed_class->getImplements()[0]->getFullPath());
    }
}
