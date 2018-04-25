<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\MemoryRecordStorage;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\RecordStorageInterface;
use Hostnet\Component\TypeInference\Analyzer\ProjectAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\DynamicAnalyzer
 */
class DynamicAnalyzerTest extends TestCase
{
    /**
     * @var DynamicAnalyzer
     */
    private $analyzer;

    /**
     * @var RecordStorageInterface
     */
    private $storage;

    /**
     * @var string
     */
    private $example_project_dir;

    protected function setUp()
    {
        $this->storage             = new MemoryRecordStorage();
        $this->analyzer            = new DynamicAnalyzer($this->storage, [ProjectAnalyzer::VENDOR_FOLDER]);
        $this->example_project_dir = dirname(__DIR__, 2) . '/Fixtures/ExampleDynamicAnalysis/Example-Project-1';
    }

    public function testDynamicAnalyzerGeneratesAnalyzedFunctions()
    {
        $results = $this->analyzer->collectAnalyzedFunctions($this->example_project_dir);

        self::assertCount(39, $results);

        $count = 0;
        $this->storage->loopEntryRecords(function () use (&$count) {
            $count++;
        });
        self::assertGreaterThan(0, $count);

        unset($this->analyzer);

        $count = 0;
        $this->storage->loopEntryRecords(function () use (&$count) {
            $count++;
        });

        self::assertSame(0, $count);
    }

    public function testWhenProvidingExistingTraceThenDoNotGenerateNewOne()
    {
        $this->analyzer = new DynamicAnalyzer(
            new MemoryRecordStorage(),
            [ProjectAnalyzer::VENDOR_FOLDER],
            null,
            dirname(__DIR__, 2) . '/Fixtures/ExampleDynamicAnalysis/Trace/example_trace.xt'
        );

        $results = $this->analyzer->collectAnalyzedFunctions($this->example_project_dir);
        self::assertEmpty($results);
    }
}
