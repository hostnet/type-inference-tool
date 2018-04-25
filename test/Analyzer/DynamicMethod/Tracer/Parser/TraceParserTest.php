<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Exception\TraceNotFoundException;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\DatabaseRecordStorage;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\MemoryRecordStorage;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Tracer;
use Hostnet\Component\TypeInference\Analyzer\ProjectAnalyzer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\TraceParser
 */
class TraceParserTest extends TestCase
{
    /**
     * @var TraceParser
     */
    private $trace_parser;

    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * @var MemoryRecordStorage
     */
    private $storage;

    /**
     * @var string
     */
    private $target_project;

    protected function setUp()
    {
        $this->storage        = new MemoryRecordStorage();
        $fixtures             = dirname(__DIR__, 4) . '/Fixtures/ExampleDynamicAnalysis';
        $this->target_project = $fixtures . '/Example-Project-1';

        $this->tracer = new Tracer(
            $this->target_project . Tracer::OUTPUT_FOLDER_NAME,
            $this->target_project,
            dirname(__DIR__, 5),
            new NullLogger()
        );
        $this->tracer->generateTrace();

        $this->trace_parser = new TraceParser(
            $this->target_project,
            $this->tracer->getFullOutputTracePath(),
            $this->storage,
            [ProjectAnalyzer::VENDOR_FOLDER],
            new NullLogger()
        );
    }

    protected function tearDown()
    {
        if (file_exists($this->tracer->getFullOutputTracePath())) {
            unlink($this->tracer->getFullOutputTracePath());
        }

        if (!file_exists($this->tracer->getFullOutputBootstrapPath())) {
            return;
        }

        unlink($this->tracer->getFullOutputBootstrapPath());
    }

    public function testParseValidTraceShouldGenerateAbstractRecords()
    {
        $this->trace_parser->parse();
        $entries = [];

        $this->storage->loopEntryRecords(
            function (EntryRecord $entry, array $params, $return_type) use (&$entries, &$returns) {
                $entries[] = $entry;
            }
        );

        $mocked_entry = null;
        foreach ($entries as $entry) {
            if ($entry->getFunctionName() !== 'ExampleProject\SomeClassTest->testSomethingWithMocks') {
                continue;
            }

            $mocked_entry = $entry;
        }

        self::assertNotNull($mocked_entry);
        self::assertCount(99, $entries);
    }

    public function testParseNonExistentTraceFile()
    {
        $this->expectException(TraceNotFoundException::class);
        $this->trace_parser = new TraceParser($this->target_project, 'Some/invalid/path', $this->storage, [
            ProjectAnalyzer::VENDOR_FOLDER,
        ], new NullLogger());
        $this->trace_parser->parse();
    }

    public function testWhenUsingDatabaseStorageThenCommitToDatabase()
    {
        $storage = $this->createMock(DatabaseRecordStorage::class);
        $storage->expects(self::exactly(1))->method('finishInsertion');

        $this->trace_parser = new TraceParser(
            $this->target_project,
            $this->tracer->getFullOutputTracePath(),
            $storage,
            [ProjectAnalyzer::VENDOR_FOLDER],
            new NullLogger()
        );
        $this->trace_parser->parse();
    }
}
