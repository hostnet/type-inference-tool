<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
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
        $analyzer = new StaticAnalyzer();
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

        $some_class_construct      = new AnalyzedFunction($some_class, '__construct');
        $some_class_get_foo        = new AnalyzedFunction($some_class, 'getFoo');
        $some_class_do_something   = new AnalyzedFunction($some_class, 'doSomething');
        $foo_interface_get_foo     = new AnalyzedFunction($foo_interface, 'getFoo');
        $abstract_foo_do_something = new AnalyzedFunction($abstract_foo, 'doSomething');
        $abstract_foo_foobar       = new AnalyzedFunction($abstract_foo, 'foobar');

        self::assertCount(6, $results);
        self::assertContains($some_class_construct, $results, false, false, false);
        self::assertContains($some_class_get_foo, $results, false, false, false);
        self::assertContains($some_class_do_something, $results, false, false, false);
        self::assertContains($foo_interface_get_foo, $results, false, false, false);
        self::assertContains($abstract_foo_do_something, $results, false, false, false);
        self::assertContains($abstract_foo_foobar, $results, false, false, false);
    }
}
