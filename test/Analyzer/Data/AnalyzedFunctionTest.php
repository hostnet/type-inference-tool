<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction
 */
class AnalyzedFunctionTest extends TestCase
{
    private $namespace     = 'Just\\Some\\Namespace';
    private $class_name    = 'MyClass';
    private $function_name = 'SomeFunction';

    /**
     * @var AnalyzedFunction
     */
    private $analyzed_function;

    protected function setUp()
    {
        $this->analyzed_function = new AnalyzedFunction($this->namespace, $this->class_name, $this->function_name);
    }

    public function testAddCollectedArgumentsShouldAddArguments()
    {
        $arguments_0 = [new PhpType('SomeObject')];
        $arguments_1 = [new PhpType('SomeObject')];

        $this->analyzed_function->addCollectedArguments(new AnalyzedCall($arguments_0));
        $this->analyzed_function->addCollectedArguments(new AnalyzedCall($arguments_1));

        self::assertSame($this->namespace, $this->analyzed_function->getNamespace());
        self::assertSame($this->class_name, $this->analyzed_function->getClassName());
        self::assertSame($this->function_name, $this->analyzed_function->getFunctionName());

        $collected_calls = $this->analyzed_function->getCollectedArguments();

        self::assertCount(2, $collected_calls);
        self::assertSame($arguments_0, $collected_calls[0]->getArguments());
        self::assertSame($arguments_1, $collected_calls[1]->getArguments());
    }

    public function testAddCollectedReturnShouldAddReturns()
    {
        $return_0 = 'string';
        $return_1 = 'int';

        $this->analyzed_function->addCollectedReturn(new AnalyzedReturn(new PhpType($return_0)));
        $this->analyzed_function->addCollectedReturn(new AnalyzedReturn(new PhpType($return_1)));

        $collected_returns = $this->analyzed_function->getCollectedReturns();

        self::assertCount(2, $collected_returns);
        self::assertSame($return_0, $collected_returns[0]->getType()->getName());
        self::assertSame($return_1, $collected_returns[1]->getType()->getName());
    }

    public function testAddAllCollectedArgumentsShouldAppendAListOfArguments()
    {
        $analyzed_calls = [
            [new PhpType('string'), new PhpType('int')],
            [new PhpType('obj'), new PhpType('float')]
        ];

        $this->analyzed_function->addAllCollectedArguments($analyzed_calls);

        self::assertSameSize($analyzed_calls, $this->analyzed_function->getCollectedArguments());
    }

    public function testAddAllCollectedReturnsShouldAppendAListOfReturns()
    {
        $analyzed_returns = [
            new AnalyzedReturn(new PhpType('SomeObject')),
            new AnalyzedReturn(new PhpType('string')),
            new AnalyzedReturn(new PhpType('float'))
        ];

        $this->analyzed_function->addAllCollectedReturns($analyzed_returns);

        self::assertSameSize($analyzed_returns, $this->analyzed_function->getCollectedReturns());
    }
}
