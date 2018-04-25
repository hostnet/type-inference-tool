<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data;

use gossi\docblock\Docblock;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
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
        $analyzed_class          = new AnalyzedClass($this->namespace, $this->class_name, '', null, [], []);
        $this->analyzed_function = new AnalyzedFunction($analyzed_class, $this->function_name);
    }

    public function testAddCollectedArgumentsShouldAddArguments()
    {
        $arguments_0 = [new NonScalarPhpType('ns', 'SomeObject', '', null, [], [])];
        $arguments_1 = [new NonScalarPhpType('ns', 'SomeObject', '', null, [], [])];

        $this->analyzed_function->addCollectedArguments(new AnalyzedCall($arguments_0));
        $this->analyzed_function->addCollectedArguments(new AnalyzedCall($arguments_1));

        self::assertSame($this->namespace, $this->analyzed_function->getClass()->getNamespace());
        self::assertSame($this->class_name, $this->analyzed_function->getClass()->getClassName());
        self::assertSame($this->namespace . '\\' . $this->class_name, $this->analyzed_function->getClass()->getFqcn());
        self::assertSame($this->function_name, $this->analyzed_function->getFunctionName());

        $collected_calls = $this->analyzed_function->getCollectedArguments();

        self::assertCount(2, $collected_calls);
        self::assertSame($arguments_0, $collected_calls[0]->getArguments());
        self::assertSame($arguments_1, $collected_calls[1]->getArguments());
        self::assertFalse($this->analyzed_function->hasReturnDeclaration());
    }

    public function testAddCollectedReturnShouldAddReturns()
    {
        $this->analyzed_function->addCollectedReturn(new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_STRING)));
        $this->analyzed_function->addCollectedReturn(new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_INT)));

        $collected_returns = $this->analyzed_function->getCollectedReturns();

        self::assertCount(2, $collected_returns);
        self::assertSame(ScalarPhpType::TYPE_STRING, $collected_returns[0]->getType()->getName());
        self::assertSame(ScalarPhpType::TYPE_INT, $collected_returns[1]->getType()->getName());
    }

    public function testAddAllCollectedArgumentsShouldAppendAListOfArguments()
    {
        $analyzed_calls = [
            [new ScalarPhpType(ScalarPhpType::TYPE_STRING), new ScalarPhpType(ScalarPhpType::TYPE_INT)],
            [new NonScalarPhpType('', 'SomeClass', '', null, []), new ScalarPhpType(ScalarPhpType::TYPE_FLOAT)],
        ];

        $this->analyzed_function->addAllCollectedArguments($analyzed_calls);

        self::assertSameSize($analyzed_calls, $this->analyzed_function->getCollectedArguments());
    }

    public function testAddAllCollectedReturnsShouldAppendAListOfReturns()
    {
        $analyzed_returns = [
            new AnalyzedReturn(new NonScalarPhpType('ns', 'SomeObject', '', null, [])),
            new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_STRING)),
            new AnalyzedReturn(new ScalarPhpType(ScalarPhpType::TYPE_FLOAT)),
        ];

        $this->analyzed_function->addAllCollectedReturns($analyzed_returns);

        self::assertSameSize($analyzed_returns, $this->analyzed_function->getCollectedReturns());
    }

    public function testSetClassShouldChangeTheClass()
    {
        $class = new AnalyzedClass('New\Namespace', 'NewClassName', 'file.php', null, []);
        $this->analyzed_function->setClass($class);

        $new_class = $this->analyzed_function->getClass();
        self::assertSame($class, $new_class);
    }

    public function testDefinedReturnTypeShouldBeUpdatedAfterBeingSet()
    {
        self::assertNull($this->analyzed_function->getDefinedReturnType());
        $this->analyzed_function->setDefinedReturnType(ScalarPhpType::TYPE_FLOAT);
        self::assertSame(ScalarPhpType::TYPE_FLOAT, $this->analyzed_function->getDefinedReturnType());
    }

    public function testDefinedParametersShouldBeUpdatedAfterBeingSet()
    {
        self::assertEmpty($this->analyzed_function->getDefinedParameters());

        $analyzed_parameters = [
            new AnalyzedParameter('$arg0', ScalarPhpType::TYPE_INT, true, true),
            new AnalyzedParameter(),
        ];
        $this->analyzed_function->setDefinedParameters($analyzed_parameters);

        self::assertSame($analyzed_parameters, $this->analyzed_function->getDefinedParameters());
    }

    public function testDocBlockShouldBeUpdatedAfterBeingSet()
    {
        self::assertNull($this->analyzed_function->getDocblock());

        $docblock = new Docblock('/** Docblock */');
        $this->analyzed_function->setDocblock($docblock);

        self::assertSame($docblock, $this->analyzed_function->getDocblock());
    }
}
