<?php
declare(strict_types=1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord;

/**
 * Uses the internal memory to store data to. Used by default.
 */
class MemoryRecordStorage implements RecordStorageInterface
{
    /**
     * @var EntryRecord[]
     */
    private $entry_records = [];

    /**
     * @var ReturnRecord[]
     */
    private $return_records = [];

    /**
     * Appends an entry record to a collection of entry records.
     *
     * @param EntryRecord $entry_record
     */
    public function appendEntryRecord(EntryRecord $entry_record)
    {
        $this->entry_records[] = $entry_record;
    }

    /**
     * Appends an entry record to a collection of entry records.
     *
     * @param ReturnRecord $return_record
     */
    public function appendReturnRecord(ReturnRecord $return_record)
    {
        $this->return_records[] = $return_record;
    }

    /**
     * Loops all trace records and executes the given callback for each found
     * record.
     *
     * @param callable $callback(EntryRecord, parameter, ReturnRecord)
     */
    public function loopEntryRecords(callable $callback)
    {
        foreach ($this->entry_records as $entry_record) {
            $matching_return = null;

            foreach ($this->return_records as $return_record) {
                if ($return_record->getNumber() !== $entry_record->getNumber()) {
                    continue;
                }

                $matching_return = $return_record->getReturnType();
                break;
            }

            $callback($entry_record, $entry_record->getParameters(), $matching_return);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishInsertion()
    {
        // No action needed
    }

    /**
     * Deletes all records.
     */
    public function clearRecords()
    {
        $this->entry_records  = [];
        $this->return_records = [];
    }
}
