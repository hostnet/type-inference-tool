<?php
declare(strict_types=1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord;

/**
 * Used by trace parser to store parsed trace data.
 */
interface RecordStorageInterface
{
    /**
     * Appends an entry record to a collection of entry records.
     *
     * @param EntryRecord $entry_record
     */
    public function appendEntryRecord(EntryRecord $entry_record);

    /**
     * Appends an entry record to a collection of entry records.
     *
     * @param ReturnRecord $return_record
     */
    public function appendReturnRecord(ReturnRecord $return_record);

    /**
     * Mark insertions as finished.
     */
    public function finishInsertion();

    /**
     * Loops all trace records and executes the given callback for each found
     * record.
     *
     * @param callable $callback
     */
    public function loopEntryRecords(callable $callback);

    /**
     * Deletes all records
     */
    public function clearRecords();
}
