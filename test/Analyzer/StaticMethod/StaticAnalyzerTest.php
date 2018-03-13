<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedParameter;
use Hostnet\Component\TypeInference\Analyzer\ProjectAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\StaticMethod\StaticAnalyzer
 */
class StaticAnalyzerTest extends TestCase
{
    /**
     * @var string
     */
    private $example_project_directory;

    protected function setUp()
    {
        $this->example_project_directory = dirname(__DIR__, 2) . '/Fixtures/ExampleStaticAnalysis/Example-Project';
    }

    public function testStaticAnalyzerGeneratesAnalyzedFunctions()
    {
        $analyzer = new StaticAnalyzer([ProjectAnalyzer::VENDOR_FOLDER]);
        $results  = $analyzer->collectAnalyzedFunctions($this->example_project_directory);

        $abstract_foo  = new AnalyzedClass(
            'ExampleStaticProject',
            'AbstractFoo',
            $this->example_project_directory . '/src/AbstractFoo.php',
            null,
            [],
            ['doSomething', 'foobar']
        );
        $foo_interface = new AnalyzedClass(
            'ExampleStaticProject',
            'FooInterface',
            $this->example_project_directory . '/src/FooInterface.php',
            null,
            [],
            ['getFoo']
        );
        $some_class    = new AnalyzedClass(
            'ExampleStaticProject',
            'SomeClass',
            $this->example_project_directory . '/src/SomeClass.php',
            $abstract_foo,
            [$foo_interface],
            ['__construct', 'getFoo', 'doSomething']
        );

        $some_class_construct      = new AnalyzedFunction(
            $some_class,
            '__construct',
            null,
            false,
            [new AnalyzedParameter('foo')]
        );
        $some_class_get_foo        = new AnalyzedFunction($some_class, 'getFoo');
        $some_class_do_something   = new AnalyzedFunction($some_class, 'doSomething');
        $foo_interface_get_foo     = new AnalyzedFunction($foo_interface, 'getFoo');
        $abstract_foo_do_something = new AnalyzedFunction($abstract_foo, 'doSomething');
        $abstract_foo_foobar       = new AnalyzedFunction($abstract_foo, 'foobar');

        self::assertCount(7, $results);
        self::assertContains($some_class_construct, $results, '', false, false);
        self::assertContains($some_class_get_foo, $results, '', false, false);
        self::assertContains($some_class_do_something, $results, '', false, false);
        self::assertContains($foo_interface_get_foo, $results, '', false, false);
        self::assertContains($abstract_foo_do_something, $results, '', false, false);
        self::assertContains($abstract_foo_foobar, $results, '', false, false);
    }

    public function testListAllMethodsShouldRetrieveAllMethodNamesOfTheGivenClassAndItsParents()
    {
        $function_index = [
            'ExampleProject\\SomeClass' => [
                'path' => 'src/SomeClass.php',
                'methods' => ['fn1', 'fn2'],
                'parents' => ['ExampleProject\\SomeClassInterface', 'SomeVendor\\AbstractSomeClass']
            ],
            'ExampleProject\\SomeClassInterface' => [
                'path' => 'src/SomeClassInterface.php',
                'methods' => ['fn3', 'fn4'],
                'parents' => []
            ],
            'SomeVendor\\AbstractSomeClass' => [
                'path' => 'vendor/AbstractSomeClass.php',
                'methods' => ['fn5', 'fn6'],
                'parents' => ['HelloWorld\\AnotherClass']
            ],
            'HelloWorld\\AnotherClass' => [
                'path' => 'vendor/HelloWorld/AnotherClass.php',
                'methods' => ['fn7', 'fn8'],
                'parents' => ['ExampleProject\\RandomClass']
            ],
        ];

        $fqcn             = 'ExampleProject\\SomeClass';
        $methods          = StaticAnalyzer::listAllMethods($function_index, $fqcn);
        $expected_methods = ['fn1', 'fn2', 'fn3', 'fn4', 'fn5', 'fn6', 'fn7','fn8'];

        self::assertEquals($expected_methods, $methods);
    }
}
