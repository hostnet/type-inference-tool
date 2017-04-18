<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

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

        $analyzed_function_0 = new AnalyzedFunction($namespace, $class_name, $function_name);
        $analyzed_function_0->addCollectedReturn(new AnalyzedReturn(new PhpType('SomeType')));
        $analyzed_function_0->addCollectedArguments(new AnalyzedCall([new PhpType('SomeType')]));

        $analyzed_function_1 = new AnalyzedFunction($namespace, $class_name, $function_name);
        $analyzed_function_1->addCollectedReturn(new AnalyzedReturn(new PhpType('AnotherType')));

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
