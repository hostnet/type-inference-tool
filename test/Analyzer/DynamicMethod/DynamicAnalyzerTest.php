<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedCall;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\DynamicAnalyzer
 */
class DynamicAnalyzerTest extends TestCase
{
    /**
     * @var DynamicAnalyzer
     */
    private $analyzer;

    /**
     * @var string
     */
    private $example_project_dir;

    protected function setUp()
    {
         $this->analyzer            = new DynamicAnalyzer();
         $this->example_project_dir = dirname(__DIR__, 2) . '/Fixtures/ExampleDynamicAnalysis/Example-Project-1';
    }

    protected function tearDown()
    {
        $file_system = new Filesystem();
        $file_system->remove($this->example_project_dir . '/output');
    }

    public function testDynamicAnalyzerGeneratesAnalyzedFunctions()
    {
        $results    = $this->analyzer->collectAnalyzedFunctions($this->example_project_dir);
        $call_int   = new AnalyzedCall([new ScalarPhpType(ScalarPhpType::TYPE_INT)]);
        $call_float = new AnalyzedCall([new ScalarPhpType(ScalarPhpType::TYPE_FLOAT)]);
        $call_cb    = new AnalyzedCall([new NonScalarPhpType('', 'callable', '', null, [], [])]);

        $some_obj              = new AnalyzedClass(
            'ExampleProject',
            'SomeObject',
            $this->example_project_dir . '/src/SomeObject.php',
            null,
            [],
            ['__construct', 'executeCallback']
        );
        $some_object_construct = new AnalyzedFunction($some_obj, '__construct');
        $some_object_construct->addAllCollectedArguments([$call_int, $call_int, $call_int, $call_int, $call_int,
            $call_float, $call_int, $call_int, $call_int, $call_int]);
        $some_object_callback = new AnalyzedFunction($some_obj, 'executeCallback');
        $some_object_callback->addAllCollectedArguments([$call_cb, $call_cb, $call_cb, $call_cb, $call_cb, $call_cb,
            $call_cb, $call_cb, $call_cb, $call_cb]);

        $some_obj_a              = new AnalyzedClass(
            'ExampleProject\A',
            'SomeObject',
            $this->example_project_dir . '/src/A/SomeObject.php',
            null,
            [],
            ['__construct', 'executeCallback']
        );
        $some_object_a_construct = new AnalyzedFunction($some_obj_a, '__construct');
        $some_object_a_construct->addAllCollectedArguments([$call_int, $call_int, $call_int, $call_int, $call_int,
            $call_int, $call_int, $call_int]);
        $some_object_a_callback = new AnalyzedFunction($some_obj_a, 'executeCallback');
        $some_object_a_callback->addAllCollectedArguments([$call_cb, $call_cb, $call_cb, $call_cb, $call_cb, $call_cb,
            $call_cb, $call_cb]);

        self::assertCount(28, $results);
        self::assertEquals($some_object_construct, $results[0]);
        self::assertEquals($some_object_callback, $results[1]);
        self::assertEquals($some_object_a_construct, $results[2]);
        self::assertEquals($some_object_a_callback, $results[3]);
    }
}
