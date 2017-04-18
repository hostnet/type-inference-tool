<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedCall;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedReturn;
use Hostnet\Component\TypeInference\Analyzer\Data\PhpType;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\ProjectAnalyzer
 */
class ProjectAnalyzerTest extends TestCase
{
    /**
     * @var ProjectAnalyzer
     */
    private $project_analyzer;

    protected function setUp()
    {
        $logger = new Logger('test-logger');
        $logger->pushProcessor(new PsrLogMessageProcessor());

        $this->project_analyzer = new ProjectAnalyzer('some\\target\\project\\', $logger);
    }

    public function testAnalyseShouldNotGenerateInstructionsWithoutAnalyzers()
    {
        self::assertEmpty($this->project_analyzer->analyse());
    }

    public function testAnalyseShouldGenerateTheCorrectInstructions()
    {
        $namespace  = 'Just\\Some\\Namespace';
        $class_name = 'ClassName';

        // Contains two type hints and one return type to be added
        $analyzed_function_0 = new AnalyzedFunction($namespace, $class_name, 'function_0');
        $analyzed_function_0->addCollectedArguments(new AnalyzedCall([new PhpType('SomeObject'), new PhpType('int')]));
        $analyzed_function_0->addCollectedArguments(new AnalyzedCall([new PhpType('SomeObject'), new PhpType('int')]));
        $analyzed_function_0->addCollectedReturn(new AnalyzedReturn(new PhpType('string')));
        $analyzed_function_0->addCollectedReturn(new AnalyzedReturn(new PhpType('string')));

        // Contains inconsistent parameter types and return type
        $analyzed_function_1 = new AnalyzedFunction($namespace, $class_name, 'function_1');
        $analyzed_function_1->addCollectedArguments(new AnalyzedCall([new PhpType('ObjectA')]));
        $analyzed_function_1->addCollectedArguments(new AnalyzedCall([new PhpType('ObjectB')]));
        $analyzed_function_1->addCollectedReturn(new AnalyzedReturn(new PhpType('string')));
        $analyzed_function_1->addCollectedReturn(new AnalyzedReturn(new PhpType('int')));

        // Contains no return value
        $analyzed_function_2 = new AnalyzedFunction($namespace, $class_name, 'function_2');

        $analyzed_functions = [$analyzed_function_0, $analyzed_function_1, $analyzed_function_2];
        $analyzer           = $this->createMock(FunctionAnalyzerInterface::class);
        $analyzer->method('collectAnalyzedFunctions')->willReturn($analyzed_functions);

        $this->project_analyzer->addAnalyzer($analyzer);
        $instructions = $this->project_analyzer->analyse('some\\project\\path\\');

        self::assertCount(3, $instructions);
    }
}
