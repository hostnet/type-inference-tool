<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedCall;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedReturn;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\PhpTypeInterface;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\UnresolvablePhpType;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\ProjectAnalyzer
 */
class ProjectAnalyzerTest extends TestCase
{
    private $target_project = 'some/target/project';

    /**
     * @var ProjectAnalyzer
     */
    private $project_analyzer;

    /**
     * @var AnalyzedFunction
     */
    private $analyzed_function;

    protected function setUp()
    {
        $this->project_analyzer  = new ProjectAnalyzer();
        $this->analyzed_function = new AnalyzedFunction(
            new AnalyzedClass('Namespace', 'SomeClass', '', null, [], ['fn']),
            'fn'
        );
    }

    public function testAnalyseShouldNotGenerateInstructionsWithoutAnalyzers()
    {
        self::assertEmpty($this->project_analyzer->analyse($this->target_project));
    }

    /**
     * @dataProvider analyzedFunctionsReturnTypeDataProvider
     * @param int $generated_instructions
     * @param PhpTypeInterface[] $types
     * @param AnalyzedFunction $analyzed_function
     */
    public function testGenerateReturnTypeInstructions(
        int $generated_instructions,
        array $types,
        AnalyzedFunction $analyzed_function = null
    ) {
        if ($analyzed_function === null) {
            $analyzed_function = $this->analyzed_function;
        }

        foreach ($types as $type) {
            $analyzed_function->addCollectedReturn(new AnalyzedReturn($type));
        }

        $this->addFunctionAnalyserMock([$analyzed_function]);
        $instructions = $this->project_analyzer->analyse($this->target_project);

        self::assertCount($generated_instructions, $instructions);
    }

    /**
     * @dataProvider analyzedFunctionsTypeHintDataProvider
     * @param int $generated_instructions
     * @param AnalyzedCall[] $types
     * @param AnalyzedFunction $analyzed_function
     */
    public function testGenerateTypeHintInstructions(
        int $generated_instructions,
        array $types,
        AnalyzedFunction $analyzed_function = null
    ) {
        if ($analyzed_function === null) {
            $analyzed_function = $this->analyzed_function;
        }

        foreach ($types as $type) {
            $analyzed_function->addCollectedArguments(new AnalyzedCall($type));
        }

        $this->addFunctionAnalyserMock([$analyzed_function]);
        $instructions = $this->project_analyzer->analyse($this->target_project);

        self::assertCount($generated_instructions, $instructions);
    }

    public function testAnalyseFunctionWithLoggingEnabledShouldSaveLogs()
    {
        $fs      = new Filesystem();
        $log_dir = dirname(__DIR__) . '/Fixtures/test-logs.log';
        $logger  = new Logger('test-logger');
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $logger->pushHandler(new StreamHandler($log_dir));
        $this->project_analyzer = new ProjectAnalyzer($logger);

        $analyzed_function_1 = new AnalyzedFunction(new AnalyzedClass('Ns', 'Someclass', 'file.php', null, []), 'fn');
        $analyzed_function_1->addCollectedArguments(new AnalyzedCall([new ScalarPhpType(ScalarPhpType::TYPE_BOOL)]));
        $analyzed_function_1->addCollectedArguments(new AnalyzedCall([new ScalarPhpType(ScalarPhpType::TYPE_FLOAT)]));
        $analyzed_function_1->addCollectedReturn(new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_BOOL)));
        $analyzed_function_1->addCollectedReturn(new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_STRING)));

        $vendor_class        = new AnalyzedClass(
            'Ns',
            'AbstractClass',
            $this->target_project . '/vendor/lib/file.php',
            null,
            [],
            ['fn']
        );
        $analyzed_function_2 = new AnalyzedFunction(
            new AnalyzedClass('Ns', 'ClassImpl', 'file.php', $vendor_class, [], ['fn']),
            'fn'
        );
        $analyzed_function_2->addCollectedArguments(new AnalyzedCall([new ScalarPhpType(ScalarPhpType::TYPE_BOOL)]));

        $this->addFunctionAnalyserMock([$analyzed_function_1, $analyzed_function_2]);
        $this->project_analyzer->analyse($this->target_project);

        $logs = file_get_contents($log_dir);
        $fs->remove($log_dir);

        self::assertContains('TYPE_HINT', $logs);
        self::assertContains('RETURN_TYPE', $logs);
        self::assertContains('IMMUTABLE_FUNCTION', $logs);
    }

    public function analyzedFunctionsReturnTypeDataProvider()
    {
        $type_int          = new ScalarPhpType(ScalarPhpType::TYPE_INT);
        $type_float        = new ScalarPhpType(ScalarPhpType::TYPE_FLOAT);
        $type_obj_a        = new NonScalarPhpType('', 'ObjA', '', null, []);
        $type_obj_b        = new NonScalarPhpType('', 'ObjB', '', null, []);
        $type_inconsistent = new UnresolvablePhpType(UnresolvablePhpType::INCONSISTENT);
        $type_none         = new UnresolvablePhpType(UnresolvablePhpType::NONE);

        $func          = 'fn';
        $vendor_parent = new AnalyzedClass('Ns', 'Clazz', $this->target_project . '/vendor/c.php', null, [], [$func]);
        $common_parent = new AnalyzedClass('Some\\Namespace', 'AbstractClass', '', null, [], [$func]);
        $interface     = new AnalyzedClass('Some\\Namespace', 'SomeInterface', '', $common_parent, [], [$func]);
        $child_1       = new NonScalarPhpType('Some\\Namespace', 'ClassA', '', $common_parent, [], [$func]);
        $child_2       = new NonScalarPhpType('Some\\Namespace', 'ClassB', '', null, [$interface], [$func]);
        $analyzed_func = new AnalyzedFunction(
            new AnalyzedClass('Namespace', 'SomeClass', '', null, [$interface], [$func]),
            $func
        );
        $vendor_child  = new AnalyzedFunction(
            new AnalyzedClass('Namespace', 'SomeClass', '', $vendor_parent, [], [$func]),
            $func
        );

        return [
            [0, [$type_int], $vendor_child],
            [3, [$type_int], $analyzed_func],
            [1, [$child_1, $child_2]],
            [1, [$type_int, $type_float]],
            [1, [$type_int, $type_int]],
            [1, [$type_obj_a, $type_obj_a]],
            [0, [$type_obj_a, $type_obj_b]],
            [0, [$type_inconsistent, $type_none]],
            [0, []]
        ];
    }

    public function analyzedFunctionsTypeHintDataProvider()
    {
        $type_bool         = new ScalarPhpType(ScalarPhpType::TYPE_BOOL);
        $type_int          = new ScalarPhpType(ScalarPhpType::TYPE_INT);
        $type_float        = new ScalarPhpType(ScalarPhpType::TYPE_FLOAT);
        $type_string       = new ScalarPhpType(ScalarPhpType::TYPE_STRING);
        $type_obj_a        = new NonScalarPhpType('', 'ObjA', '', null, []);
        $type_obj_b        = new NonScalarPhpType('', 'ObjB', '', null, []);
        $type_inconsistent = new UnresolvablePhpType(UnresolvablePhpType::INCONSISTENT);
        $type_none         = new UnresolvablePhpType(UnresolvablePhpType::NONE);

        $func          = 'fn';
        $common_parent = new AnalyzedClass('Some\\Namespace', 'AbstractClass', '', null, []);
        $interface     = new AnalyzedClass('Some\\Namespace', 'SomeInterface', '', $common_parent, [], [$func]);

        $extends_parent_class    = new NonScalarPhpType('Some\\Namespace', 'ClassA', '', $common_parent, []);
        $implements_parent_class = new NonScalarPhpType('Some\\Namespace', 'ClassB', '', null, [$interface]);
        $analyzed_child_function = new AnalyzedFunction(
            new AnalyzedClass('', 'SomeClass', '', null, [$interface], [$func]),
            $func
        );
        $vendor_parent           = new AnalyzedClass(
            'Some\\Namespace',
            'ClazzInterface',
            $this->target_project . '/vendor/lib/ClazzInterface.php',
            null,
            [],
            [$func]
        );

        $abstract_implements_vendor = new AnalyzedClass('', 'AbstractClass', '', null, [$vendor_parent], [$func]);
        $analyzed_function          = new AnalyzedFunction(
            new AnalyzedClass('Namespace', 'Clazz', '', $abstract_implements_vendor, [], [$func]),
            $func
        );

        return [
            [0, [[$type_string], [$type_bool]], $analyzed_function],
            [2, [[$type_bool]], $analyzed_child_function],
            [1, [[$extends_parent_class], [$implements_parent_class]]],
            [2, [[$type_int, $type_int], [$type_int, $type_int]]],
            [1, [[$type_int], [$type_float]]],
            [0, [[$type_int, $type_int],[$type_string, $type_string]]],
            [2, [[$type_obj_a, $type_obj_b], [$type_obj_a, $type_obj_b]]],
            [0, [[$type_obj_a, $type_obj_b], [$type_obj_b, $type_obj_a]]],
            [0, [[$type_inconsistent, $type_none]]]
        ];
    }

    /**
     * @param AnalyzedFunction[] $return
     */
    private function addFunctionAnalyserMock(array $return)
    {
        $analyzer = $this->createMock(FunctionAnalyzerInterface::class);
        $analyzer->method('collectAnalyzedFunctions')->willReturn($return);

        $this->project_analyzer->addAnalyzer($analyzer);
    }
}
