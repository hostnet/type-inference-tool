<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedCall;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedParameter;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedReturn;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\PhpTypeInterface;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\UnresolvablePhpType;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\ReturnTypeInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\TypeHintInstruction;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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

    public function testGenerateTypeHintInstructionForFunctionWithParentDefinitionShouldModifyParent()
    {
        $type_int          = new ScalarPhpType(ScalarPhpType::TYPE_INT);
        $interface         = new AnalyzedClass('Namespace', 'SomeClassInterface', 'file2.php', null, [], ['foobar']);
        $analyzed_class    = new AnalyzedClass('Namespace', 'SomeClass', '/file.php', null, [$interface], ['foobar']);
        $analyzed_function = new AnalyzedFunction($analyzed_class, 'foobar');

        $analyzed_function->addCollectedArguments(new AnalyzedCall([$type_int]));

        $this->addFunctionAnalyserMock([$analyzed_function]);
        $instructions = $this->project_analyzer->analyse($this->target_project);

        $expected_parent_instruction = new TypeHintInstruction($interface, 'foobar', 0, $type_int);
        $expected_class_instruction  = new TypeHintInstruction($analyzed_class, 'foobar', 0, $type_int);

        self::assertCount(2, $instructions);
        self::assertContains($expected_parent_instruction, $instructions, false, false, false);
        self::assertContains($expected_class_instruction, $instructions, false, false, false);
    }

    public function testDoNotGenerateReturnTypeInstructionsWhenTypeNotSameAsParent()
    {
        $interface        = new AnalyzedClass('Namespace', 'SomeClassInterface', 'file1.php', null, [], ['foobar']);
        $interface_method = new AnalyzedFunction($interface, 'foobar', ScalarPhpType::TYPE_STRING, true);

        $child_class        = new AnalyzedClass('Namespace', 'Class1', 'file1.php', null, [$interface], ['foobar']);
        $child_class_method = new AnalyzedFunction($child_class, 'foobar');
        $child_class_method->addCollectedReturn(new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_BOOL)));

        $this->addFunctionAnalyserMock([$interface_method, $child_class_method]);
        $instructions = $this->project_analyzer->analyse($this->target_project);

        self::assertEmpty($instructions);
    }

    public function testDoNotGenerateParentInstructionWhenChildrenReturnDifferentReturnTypes()
    {
        $type_bool   = new ScalarPhpType(ScalarPhpType::TYPE_BOOL);
        $type_string = new ScalarPhpType(ScalarPhpType::TYPE_STRING);

        $interface        = new AnalyzedClass('Namespace', 'SomeClassInterface', 'file1.php', null, [], ['foobar']);
        $interface_method = new AnalyzedFunction($interface, 'foobar');

        $child_class1        = new AnalyzedClass('Namespace', 'Class1', 'file1.php', null, [$interface], ['foobar']);
        $child_class1_method = new AnalyzedFunction($child_class1, 'foobar');
        $child_class1_method->addCollectedReturn(new AnalyzedReturn($type_bool));

        $child_class2        = new AnalyzedClass('Namespace', 'Class2', 'file2.php', null, [$interface], ['foobar']);
        $child_class2_method = new AnalyzedFunction($child_class2, 'foobar');
        $child_class2_method->addCollectedReturn(new AnalyzedReturn($type_string));

        $this->addFunctionAnalyserMock([$interface_method, $child_class1_method, $child_class2_method]);
        $instructions = $this->project_analyzer->analyse($this->target_project);

        $expected_instruction_1 = new ReturnTypeInstruction($child_class1, 'foobar', $type_bool);
        $expected_instruction_2 = new ReturnTypeInstruction($child_class2, 'foobar', $type_string);

        self::assertCount(2, $instructions);
        self::assertContains($expected_instruction_1, $instructions, false, false, false);
        self::assertContains($expected_instruction_2, $instructions, false, false, false);
    }

    public function testWhenParentHasNoReturnTypeOnlyChildrenWithResolvableReturnTypeShouldHaveDeclaration()
    {
        $interface        = new AnalyzedClass('Namespace', 'FooInterface', '/file0.php', null, [], ['bar']);
        $interface_method = new AnalyzedFunction($interface, 'bar');

        $child_1        = new AnalyzedClass('Namespace', 'FooImpl1', '/file1.php', null, [$interface], ['bar']);
        $child_1_method = new AnalyzedFunction($child_1, 'bar');
        $child_1_method->addCollectedReturn(new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_STRING)));

        $child_2        = new AnalyzedClass('Namespace', 'FooImpl2', '/file2.php', null, [$interface], ['bar']);
        $child_2_method = new AnalyzedFunction($child_2, 'bar');
        $child_2_method->addCollectedReturn(new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_STRING)));
        $child_2_method->addCollectedReturn(new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_INT)));
        $child_2_method->addCollectedArguments(new AnalyzedCall([new ScalarPhpType(ScalarPhpType::TYPE_FLOAT)]));

        $this->addFunctionAnalyserMock([$interface_method, $child_1_method, $child_2_method]);
        $instructions = $this->project_analyzer->analyse($this->target_project);

         self::assertCount(3, $instructions);
         self::assertSame('Namespace\\FooImpl1', $instructions[0]->getTargetClass()->getFqcn());
    }

    public function testGenerateReturnTypeInstructionsWhenReturnTypeSameAsParent()
    {
        $type_string = new ScalarPhpType(ScalarPhpType::TYPE_STRING);

        $interface        = new AnalyzedClass('Namespace', 'SomeClassInterface', 'file1.php', null, [], ['foobar']);
        $interface_method = new AnalyzedFunction($interface, 'foobar', ScalarPhpType::TYPE_STRING);

        $child_class        = new AnalyzedClass('Namespace', 'SomeClass', 'file2.php', null, [$interface], ['foobar']);
        $child_class_method = new AnalyzedFunction($child_class, 'foobar');
        $child_class_method->addCollectedReturn(new AnalyzedReturn($type_string));

        $this->addFunctionAnalyserMock([$interface_method, $child_class_method]);
        $instructions = $this->project_analyzer->analyse($this->target_project);

        $expected_interface_instruction   = new ReturnTypeInstruction($interface, 'foobar', $type_string);
        $expected_child_class_instruction = new ReturnTypeInstruction($child_class, 'foobar', $type_string);

        self::assertCount(2, $instructions);
        self::assertContains($expected_interface_instruction, $instructions, false, false, false);
        self::assertContains($expected_child_class_instruction, $instructions, false, false, false);
    }

    public function testDoNotGenerateParentAndChildrenTypeHintsWhenTheyAreDifferent()
    {
        $type_string = new ScalarPhpType(ScalarPhpType::TYPE_STRING);
        $type_int    = new ScalarPhpType(ScalarPhpType::TYPE_INT);

        $interface        = new AnalyzedClass('Namespace', 'ClazzInterface', 'file0.php', null, [], ['foobar']);
        $interface_method = new AnalyzedFunction($interface, 'foobar', null, false, [
            new AnalyzedParameter(), new AnalyzedParameter()
        ]);

        $child1_class        = new AnalyzedClass('Namespace', 'Clazz1', 'file1.php', null, [$interface], ['foobar']);
        $child1_class_method = new AnalyzedFunction($child1_class, 'foobar', null, false, [
            new AnalyzedParameter(), new AnalyzedParameter()
        ]);
        $child1_class_method->addCollectedArguments(new AnalyzedCall([$type_int, $type_int]));

        $child2_class        = new AnalyzedClass('Namespace', 'Clazz2', 'file2.php', null, [$interface], ['foobar']);
        $child2_class_method = new AnalyzedFunction($child2_class, 'foobar', null, false, [
            new AnalyzedParameter(), new AnalyzedParameter()
        ]);
        $child2_class_method->addCollectedArguments(new AnalyzedCall([$type_int, $type_string]));

        $class_parent = new AnalyzedClass('Ns', 'AbstractClazz', 'File.clzz', null, [], ['foobar']);
        $class        = new AnalyzedClass('Ns', 'Clazz', 'File.clzz', null, [$class_parent], ['foobar']);
        $class_method = new AnalyzedFunction($class, 'foobar', null, false, [new AnalyzedParameter()]);

        $this->addFunctionAnalyserMock([$interface_method, $child1_class_method, $child2_class_method, $class_method]);
        $instructions = $this->project_analyzer->analyse($this->target_project);

        $invalid_instruction_1 = new TypeHintInstruction($interface, 'foobar', 1, $type_int, new NullLogger());
        $invalid_instruction_2 = new TypeHintInstruction($child1_class, 'foobar', 1, $type_int, new NullLogger());
        $invalid_instruction_3 = new TypeHintInstruction($child2_class, 'foobar', 1, $type_string, new NullLogger());

        self::assertNotContains($invalid_instruction_1, $instructions, false, false, false);
        self::assertNotContains($invalid_instruction_2, $instructions, false, false, false);
        self::assertNotContains($invalid_instruction_3, $instructions, false, false, false);
    }

    public function testAnalyseFunctionWithLoggingEnabledShouldSaveLogs()
    {
        $fs      = new Filesystem();
        $log_dir = dirname(__DIR__) . '/Fixtures/test-logs.log';
        $logger  = new Logger('test-logger');
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $logger->pushHandler(new StreamHandler($log_dir));
        $this->project_analyzer->setLogger($logger);

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

    public function analyzedFunctionsReturnTypeDataProvider(): array
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

    public function analyzedFunctionsTypeHintDataProvider(): array
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
