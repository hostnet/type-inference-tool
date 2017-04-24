<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
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
}
