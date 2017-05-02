<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Exception\TraceNotFoundException;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\TraceParser
 */
class TraceParserTest extends TestCase
{
    private $example_trace = 'example_trace.xt';

    /**
     * @var TraceParser
     */
    private $trace_parser;

    protected function setUp()
    {
        $fixtures           = dirname(__DIR__, 4) . '/Fixtures/ExampleDynamicAnalysis';
        $this->trace_parser = new TraceParser($fixtures . '/Trace/' . $this->example_trace);
    }

    public function testParseValidTraceShouldGenerateAbstractRecords()
    {
        $parsed_trace = $this->trace_parser->parse();

        self::assertCount(2, $parsed_trace);

        $entry = $parsed_trace[TraceParser::ENTRY_RECORD_NAME][0];
        self::assertInstanceOf(EntryRecord::class, $entry);
        self::assertSame(1, $entry->getNumber());
        self::assertSame('someFunction', $entry->getFunctionName());
        self::assertTrue($entry->isUserDefined());
        self::assertSame('/path/to/file/SomeFunctions.php', $entry->getFileName());
        self::assertCount(2, $entry->getParameters());
        self::assertSame('string(10)', $entry->getParameters()[0]);
        self::assertSame('array(0)', $entry->getParameters()[1]);

        $return = $parsed_trace[TraceParser::RETURN_RECORD_NAME][0];
        self::assertInstanceOf(ReturnRecord::class, $return);
        self::assertSame(1, $return->getNumber());
        self::assertSame('string(10)', $return->getReturnValue());
    }

    public function testParseNonExistentTraceFile()
    {
        $this->expectException(TraceNotFoundException::class);
        $this->trace_parser = new TraceParser('some/invalid/path/non_existent.xt');
        $this->trace_parser->parse();
    }
}
