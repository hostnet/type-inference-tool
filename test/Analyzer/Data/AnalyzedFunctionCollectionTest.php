<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\ReturnTypeInstruction;
use Hostnet\Component\TypeInference\CodeEditor\Instruction\TypeHintInstruction;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection
 */
class AnalyzedFunctionCollectionTest extends TestCase
{
    /**
     * @var AnalyzedFunctionCollection
     */
    private $collection;

    protected function setUp()
    {
        $this->collection = new AnalyzedFunctionCollection();
    }

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

        $this->collection->addAll([$analyzed_function_0, $analyzed_function_1]);

        $amount_functions = 0;
        foreach ($this->collection as $i => $analyzed_function) {
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

        $this->collection->add($class_method_1);
        $this->collection->add($class_method_2);

        foreach ($this->collection as $analyzed_function) {
            $methods_in_class = $analyzed_function->getClass()->getMethods();
            self::assertCount(2, $methods_in_class);
            self::assertContains('Function1', $methods_in_class);
            self::assertContains('Function2', $methods_in_class);
        }

        self::assertCount(2, $this->collection->getAll());
    }

    public function testAddAllShouldMergeAnalyzedFunctions()
    {
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

        $this->collection->addAll($analyzed_functions_0);
        $this->collection->addAll($analyzed_functions_1);
        $results = $this->collection->getAll();

        self::assertCount(1, $results);
        self::assertEquals($expected, $results);
    }

    public function testApplyInstructionsShouldApplyInstructionsToAnalyzedFunctions()
    {
        $class    = new AnalyzedClass('Namespace', 'ClassName', 'file.php', null, [], ['foobar']);
        $function = new AnalyzedFunction($class, 'foobar', null, false, [new AnalyzedParameter()]);
        $this->collection->add($function);

        $return_type             = new ScalarPhpType(ScalarPhpType::TYPE_FLOAT);
        $return_type_instruction = new ReturnTypeInstruction($class, 'foobar', $return_type);

        $parameter_type        = new ScalarPhpType(ScalarPhpType::TYPE_STRING);
        $type_hint_instruction = new TypeHintInstruction($class, 'foobar', 0, $parameter_type);

        $this->collection->applyInstructions([$return_type_instruction, $type_hint_instruction]);

        self::assertSame($return_type->getName(), $this->collection->getAll()[0]->getDefinedReturnType());
        self::assertSame(
            $parameter_type->getName(),
            $this->collection->getAll()[0]->getDefinedParameters()[0]->getType()
        );
    }

    public function testGetFunctionChildrenShouldReturnAllChildClassesImplementingAFunction()
    {
        $parent_class = new AnalyzedClass('Namespace', 'ClazzInterface', 'File1.php', null, [], ['foobar']);

        $child          = new AnalyzedClass('Namespace', 'Clazz', 'File2.php', null, [$parent_class], ['foobar']);
        $child_function = new AnalyzedFunction($child, 'foobar');

        $other_class_1          = new AnalyzedClass('Namespace', 'Foobar', 'File3.php', null, [], ['foobar']);
        $other_class_1_function = new AnalyzedFunction($other_class_1, 'foobar');

        $other_class_2          = new AnalyzedClass('Namespace', 'AbstractClazz', 'File3.php', null, []);
        $other_class_2_function = new AnalyzedFunction($other_class_2, 'someFunction');

        $this->collection->addAll([$child_function, $other_class_1_function, $other_class_2_function]);

        $parent_children = $this->collection->getFunctionChildren($parent_class, 'foobar');

        self::assertCount(1, $parent_children);
        self::assertSame($child, $parent_children[0]);
    }
}
