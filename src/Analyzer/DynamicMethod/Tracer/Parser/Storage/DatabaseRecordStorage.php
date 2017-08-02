<?php
declare(strict_types=1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord;
use Hostnet\Component\TypeInference\Tool\Tool;

/**
 * Uses a database to store data.
 */
class DatabaseRecordStorage implements RecordStorageInterface
{
    /**
     * Maximum amount of records in a batch. When this amount is reached, the
     * batch should be committed.
     */
    const RECORDS_PER_BATCH = 2000;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string[]
     */
    private $entry_record_batch = [];

    /**
     * @var string[]
     */
    private $entry_record_parameter_batch = [];

    /**
     * @var string[]
     */
    private $return_record_batch = [];

    /**
     * Indication whether records are being inserted. Used to handle insertion
     * with batches.
     *
     * @var bool
     */
    private $is_inserting = false;

    /**
     * Sets up a database connection.
     *
     * @param string $database_config_location
     */
    public function __construct(string $database_config_location)
    {
        if (!file_exists($database_config_location)) {
            throw new \InvalidArgumentException('No database config found.');
        }

        $params           = json_decode(file_get_contents($database_config_location), true);
        $this->connection = DriverManager::getConnection($params);
    }

    /**
     * Appends an EntryRecord to a batch of records. When the batch has reached
     * its limit, it will be committed and a new batch will be created.
     *
     * @param EntryRecord $entry_record
     */
    public function appendEntryRecord(EntryRecord $entry_record)
    {
        $this->startInserting();
        $this->addToEntryRecordInsertBatch($entry_record);

        if (count($this->entry_record_batch) >= self::RECORDS_PER_BATCH) {
            $this->insertEntryRecordBatch();
            $this->insertReturnRecordBatch();
        }
    }

    /**
     * Add a ReturnRecord to a batch. When the EntryRecord batch has reached
     * its limit, these return records will also be commited.
     *
     * @param ReturnRecord $return_record
     */
    public function appendReturnRecord(ReturnRecord $return_record)
    {
        $this->startInserting();
        $this->addToReturnRecordInsertBatch($return_record);
    }

    /**
     * By finishing the insertion the remaining records in the batches will be
     * committed.
     */
    public function finishInsertion()
    {
        $this->is_inserting = false;

        $this->insertEntryRecordBatch();
        $this->insertReturnRecordBatch();
        $this->commitTransaction();
    }

    /**
     * Sets an indication that the storage is inserting records and starts a
     * transaction. Use this alongside the finishInsertion-method to commit
     * data after starting a transaction.
     */
    private function startInserting()
    {
        if (!$this->is_inserting) {
            $this->is_inserting = true;
            $this->connection->beginTransaction();
        }
    }

    /**
     * Stops the open transactions and removes all database records for the current
     * execution.
     *
     * @throws DBALException
     */
    public function clearRecords()
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->connection->delete('return_record', ['execution_id' => Tool::getExecutionId()]);
        $this->connection->delete('entry_record_parameter', ['execution_id' => Tool::getExecutionId()]);
        $this->connection->delete('entry_record', ['execution_id' => Tool::getExecutionId()]);
    }

    /**
     * Loops all committed entry records. For each record a callback is invoked.
     * This callback provides a EntryRecord, array of parameters and matching
     * ReturnType.
     *
     * @param callable $callback(EntryRecord, parameter, ReturnRecord)
     * @throws DBALException
     */
    public function loopEntryRecords(callable $callback)
    {
        $stmt = $this->connection->prepare(<<<'sql'
SELECT
    e.function_nr,
    e.function_name,
    e.is_user_defined,
    e.file_name,
    e.declaration_file,
    r.return_type,
    (
        SELECT GROUP_CONCAT(p.param_type)
        FROM entry_record_parameter p
        WHERE p.execution_id = e.execution_id AND p.function_nr = e.function_nr
        GROUP BY p.execution_id, p.function_nr
        ORDER BY p.param_number
    ) AS parameters
FROM
    entry_record e
        LEFT OUTER JOIN return_record r
            ON e.execution_id = r.execution_id AND e.function_nr = r.function_nr
WHERE
    e.execution_id=:execution_id;
sql
        );

        $stmt->execute(['execution_id' => Tool::getExecutionId()]);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $parameters = $row['parameters'] === null ? [] : explode(',', $row['parameters']);

            $entry_record = new EntryRecord(
                (int) $row['function_nr'],
                $row['function_name'],
                (int) $row['is_user_defined'] === 1,
                $row['file_name'],
                $parameters
            );

            $function_declaration_file = $row['declaration_file'];

            if (null !== $function_declaration_file) {
                $entry_record->setFunctionDeclarationFile($function_declaration_file);
            }

            $callback($entry_record, $parameters, $row['return_type']);
        }
    }

    /**
     * Commits the current transaction in case there's an open one.
     *
     * @return bool
     */
    public function commitTransaction(): bool
    {
        try {
            $this->connection->commit();
        } catch (ConnectionException $e) {
            return false;
        }

        return true;
    }

    /**
     * Adds an EntryRecord to a batch that is inserted later on.
     *
     * @param EntryRecord $record
     */
    private function addToEntryRecordInsertBatch(EntryRecord $record)
    {
        $declaration_file           = $record->getFunctionDeclarationFile();
        $this->entry_record_batch[] = [
            Tool::getExecutionId(),
            $record->getNumber(),
            $record->getFunctionName(),
            $record->isUserDefined() ? '1' : '0',
            $record->getFileName(),
            $declaration_file !== null ? $declaration_file : 'null'
        ];

        $this->addToEntryRecordParameterInsertBatch($record);
    }

    /**
     * Creates a prepared statement based on the given data set for the given
     * table and executes it.
     *
     * @param string $table_name
     * @param string[] $columns
     * @param string[][] $data_set
     */
    private function insertBatch(string $table_name, array $columns, array $data_set)
    {
        $data_to_insert = [];
        foreach ($data_set as $row => $data) {
            foreach ($data as $val) {
                $data_to_insert[] = $val;
            }
        }

        $row_places = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $all_places = implode(', ', array_fill(0, count($data_set), $row_places));
        $stmt       = 'INSERT INTO ' . $table_name . '(' . implode(', ', $columns) . ') VALUES ' . $all_places . ';';

        $this->connection->prepare($stmt)->execute($data_to_insert);
    }

    /**
     * Inserts all entry records from the entry records batch.
     *
     * @throws DBALException
     */
    private function insertEntryRecordBatch()
    {
        if (count($this->entry_record_batch) === 0) {
            return;
        }

        $columns = ['execution_id', 'function_nr', 'function_name', 'is_user_defined', 'file_name', 'declaration_file'];
        $this->insertBatch('entry_record', $columns, $this->entry_record_batch);

        $this->entry_record_batch = [];
        $this->insertEntryRecordParameterBatch();
    }

    /**
     * Appends an EntryRecord to a batch of entry records. This batch could be
     * inserted later on.
     *
     * @param EntryRecord $record
     */
    private function addToEntryRecordParameterInsertBatch(EntryRecord $record)
    {
        foreach ($record->getParameters() as $arg_nr => $parameter) {
            $this->entry_record_parameter_batch[] = [
                Tool::getExecutionId(),
                $record->getNumber(),
                $arg_nr,
                $parameter
            ];
        }
    }

    /**
     * Inserts a batch of EntryRecord parameters.
     *
     * @throws DBALException
     */
    private function insertEntryRecordParameterBatch()
    {
        if (count($this->entry_record_parameter_batch) === 0) {
            return;
        }

        $columns = ['execution_id', 'function_nr', 'param_number', 'param_type'];
        $this->insertBatch('entry_record_parameter', $columns, $this->entry_record_parameter_batch);
        $this->entry_record_parameter_batch = [];
    }

    /**
     * Adds a ReturnRecord to a batch of return records. This batch could be
     * inserted later on.
     *
     * @param ReturnRecord $record
     */
    private function addToReturnRecordInsertBatch(ReturnRecord $record)
    {
        $this->return_record_batch[] = [Tool::getExecutionId(), $record->getNumber(), $record->getReturnType()];
    }

    /**
     * Inserts a batch of return records.
     *
     * @throws DBALException
     */
    private function insertReturnRecordBatch()
    {
        if (count($this->return_record_batch) === 0) {
            return;
        }

        $columns = ['execution_id', 'function_nr', 'return_type'];
        $this->insertBatch('return_record', $columns, $this->return_record_batch);
        $this->return_record_batch = [];
    }
}
