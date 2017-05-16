<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\ReturnTypeInstruction;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection
 */
class AnalyzedFunctionCollectionTest extends TestCase
{
    public function testAddAllFunctionsShouldAddAllNonDuplicates()
    {
        $namespace     = 'Just\\Some\\Namespace';
        $class_name    = 'SomeClass';
        $function_name = 'SomeFunction';

        $class = new AnalyzedClass($namespace, $class_name, '', null, [], []);

        $analyzed_function_0 = new AnalyzedFunction($class, $function_name);
        $analyzed_function_0->addCollectedReturn(new AnalyzedReturn(new NonScalarPhpType('', 'ObjA', '', null, [])));
        $analyzed_function_0->addCollectedArguments(new AnalyzedCall([new NonScalarPhpType('', 'ObjA', '', null, [])]));

        $analyzed_function_1 = new AnalyzedFunction($class, $function_name);
        $analyzed_function_1->addCollectedReturn(new AnalyzedReturn(new NonScalarPhpType('', 'ObjB', '', null, [])));

        $analyzed_function_collection = new AnalyzedFunctionCollection();
        $analyzed_function_collection->addAll([$analyzed_function_0, $analyzed_function_1]);

        $amount_functions = 0;
        foreach ($analyzed_function_collection as $i => $analyzed_function) {
            $amount_functions = $i + 1;
            self::assertCount(2, $analyzed_function->getCollectedReturns());
            self::assertCount(1, $analyzed_function->getCollectedArguments());
        }

        self::assertSame(1, $amount_functions);
    }

    public function testMethodsInClassShouldHaveSameClassObject()
    {
        $class_0 = new AnalyzedClass('Namespace', 'ClassName', 'Class.php', null, []);
        $class_1 = new AnalyzedClass('Namespace', 'ClassName', 'Class.php', null, []);

        $class_method_1 = new AnalyzedFunction($class_0, 'Function1');
        $class_method_2 = new AnalyzedFunction($class_1, 'Function2');

        $collection = new AnalyzedFunctionCollection();
        $collection->add($class_method_1);
        $collection->add($class_method_2);

        foreach ($collection as $analyzed_function) {
            $methods_in_class = $analyzed_function->getClass()->getMethods();
            self::assertCount(2, $methods_in_class);
            self::assertContains('Function1', $methods_in_class);
            self::assertContains('Function2', $methods_in_class);
        }

        self::assertCount(2, $collection->getAll());
    }

    public function testAddAllShouldMergeAnalyzedFunctions()
    {
        $collection = new AnalyzedFunctionCollection();

        $extended             = new AnalyzedClass('Namespace', 'AbstractClassName', 'file2.php');
        $implemented          = new AnalyzedClass('Namespace', 'ClassNameInterface', 'file3.php');
        $analyzed_functions_0 = [new AnalyzedFunction(new AnalyzedClass('Namespace', 'ClassName'), 'foobar')];
        $analyzed_functions_1 = [
            new AnalyzedFunction(
                new AnalyzedClass('Namespace', 'ClassName', 'file.php', $extended, [$implemented]),
                'foobar'
            )
        ];
        $expected             = [
            new AnalyzedFunction(
                new AnalyzedClass('Namespace', 'ClassName', 'file.php', $extended, [$implemented], ['foobar']),
                'foobar'
            )
        ];

        $collection->addAll($analyzed_functions_0);
        $collection->addAll($analyzed_functions_1);
        $results = $collection->getAll();

        self::assertCount(1, $results);
        self::assertEquals($expected, $results);
    }

    public function testApplyInstructionsShouldApplyInstructionsToAnalyzedFunctions()
    {
        $collection = new AnalyzedFunctionCollection();

        $class    = new AnalyzedClass('Namespace', 'ClassName', 'file.php', null, [], ['foobar']);
        $function = new AnalyzedFunction($class, 'foobar');
        $collection->add($function);

        $return_type = new ScalarPhpType(ScalarPhpType::TYPE_FLOAT);
        $instruction = new ReturnTypeInstruction($class, 'foobar', $return_type);

        $collection->applyInstructions([$instruction]);

        self::assertSame($return_type->getName(), $collection->getAll()[0]->getDefinedReturnType());
    }
}
