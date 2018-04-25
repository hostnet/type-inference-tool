<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\FileRecordStorage
 */
class FileRecordStorageTest extends TestCase
{
    /**
     * @var FileRecordStorage
     */
    private $storage;

    /**
     * @var EntryRecord
     */
    private $entry_record;

    /**
     * @var ReturnRecord
     */
    private $return_record;

    protected function setUp()
    {
        $this->storage       = new FileRecordStorage();
        $function_number     = 123;
        $this->entry_record  = new EntryRecord($function_number, 'functionName', false, 'file.php', ['arg0']);
        $this->return_record = new ReturnRecord($function_number, 'SomeType');
    }

    protected function tearDown()
    {
        $this->storage->clearRecords();
    }

    public function testWhenAppendingEntryRecordThenFileShouldBeAppendedWithRecord()
    {
        self::assertFileNotExists($this->storage->getEntryRecordFileLocation());
        $this->storage->appendEntryRecord($this->entry_record);
        $this->storage->finishInsertion();
        self::assertFileExists($this->storage->getEntryRecordFileLocation());

        $this->storage->loopEntryRecords(function (EntryRecord $record, array $params, $return_type) {
            self::assertEquals($this->entry_record, $record);
            self::assertNull($return_type);
        });

        $this->storage->clearRecords();
    }

    public function testWhenAppendingReturnRecordThenFileShouldBeAppendedWithRecord()
    {
        self::assertFileNotExists($this->storage->getReturnRecordFileLocation());

        $this->storage->appendEntryRecord($this->entry_record);
        $this->storage->appendReturnRecord($this->return_record);

        self::assertFileExists($this->storage->getReturnRecordFileLocation());

        $this->storage->loopEntryRecords(function (EntryRecord $record, array $params, $return_type) {
            self::assertSame($this->return_record->getReturnType(), $return_type);
        });

        $this->storage->clearRecords();
    }
}
