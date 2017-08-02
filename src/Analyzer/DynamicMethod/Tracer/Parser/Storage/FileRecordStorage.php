<?php
declare(strict_types=1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord;
use Hostnet\Component\TypeInference\Tool\Tool;

/**
 * Uses an external file to store data to.
 */
class FileRecordStorage implements RecordStorageInterface
{
    /**
     * @var string
     */
    private $entry_records_file;

    /**
     * @var string
     */
    private $return_records_file;

    public function __construct()
    {
        $this->entry_records_file  = 'entry_records_' . Tool::getExecutionId();
        $this->return_records_file = 'return_records_' . Tool::getExecutionId();
    }

    /**
     * Appends an entry record to a collection of entry records.
     *
     * @param EntryRecord $entry_record
     */
    public function appendEntryRecord(EntryRecord $entry_record)
    {
        file_put_contents($this->entry_records_file, serialize($entry_record) . PHP_EOL, FILE_APPEND);
    }

    /**
     * Appends an entry record to a collection of entry records.
     *
     * @param ReturnRecord $return_record
     */
    public function appendReturnRecord(ReturnRecord $return_record)
    {
        file_put_contents($this->return_records_file, serialize($return_record) . PHP_EOL, FILE_APPEND);
    }

    /**
     * Loops all trace records and executes the given callback for each found
     * record.
     *
     * @param callable $callback(EntryRecord, parameter, ReturnRecord)
     * @throws \RuntimeException
     */
    public function loopEntryRecords(callable $callback)
    {
        $entry_records_reader = fopen($this->entry_records_file, 'rb');

        while (($entry_line = fgets($entry_records_reader)) !== false) {
            $entry_record = unserialize($entry_line, [EntryRecord::class]);

            if (!file_exists($this->return_records_file)) {
                $callback($entry_record, $entry_record->getParameters(), null);
                continue;
            }

            $return_record_reader = fopen($this->return_records_file, 'rb');
            $matching_return      = null;

            while (($return_line = fgets($return_record_reader)) !== false) {
                $return_record = unserialize($return_line, [ReturnRecord::class]);

                if ($return_record->getNumber() === $entry_record->getNumber()) {
                    $matching_return = $return_record->getReturnType();
                    break;
                }
            }

            $callback($entry_record, $entry_record->getParameters(), $matching_return);
        }

        fclose($entry_records_reader);
    }

    /**
     * Removes the files containing the stored records.
     */
    public function clearRecords()
    {
        if (file_exists($this->entry_records_file)) {
            unlink($this->entry_records_file);
        }

        if (file_exists($this->return_records_file)) {
            unlink($this->return_records_file);
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
     * @return string
     */
    public function getEntryRecordFileLocation(): string
    {
        return $this->entry_records_file;
    }

    /**
     * @return string
     */
    public function getReturnRecordFileLocation(): string
    {
        return $this->return_records_file;
    }
}
