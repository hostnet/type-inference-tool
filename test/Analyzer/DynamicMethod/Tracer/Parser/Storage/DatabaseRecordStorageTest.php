<?php
declare(strict_types=1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage;

use Doctrine\DBAL\DriverManager;
use Hostnet\Component\DatabaseTest\MysqlPersistentConnection;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\DatabaseRecordStorage
 */
class DatabaseRecordStorageTest extends TestCase
{
    /**
     * @var DatabaseRecordStorage
     */
    private static $storage;

    /**
     * @var string
     */
    private static $db_config_file;

    /**
     * @var MysqlPersistentConnection
     */
    private static $conn;

    /**
     * @var EntryRecord
     */
    private $entry_record;

    /**
     * @var ReturnRecord
     */
    private $return_record;

    /**
     * @var string[]
     */
    private $params = ['$arg0'];

    protected function setUp()
    {
        $function_number     = 2001;
        $this->entry_record  = new EntryRecord($function_number, 'someFunction', true, 'file.php', $this->params);
        $this->return_record = new ReturnRecord($function_number, ScalarPhpType::TYPE_STRING);
        $this->entry_record->setFunctionDeclarationFile('scr/file.php');
    }

    protected function tearDown()
    {
        self::$storage->clearRecords();
    }

    public static function setUpBeforeClass()
    {
        self::$conn = new MysqlPersistentConnection();

        $definition_script = file_get_contents(dirname(__DIR__, 6) . '/database/DDL.sql');
        $connection        = DriverManager::getConnection(self::$conn->getConnectionParams());
        $connection->getDatabasePlatform()->registerDoctrineTypeMapping('bit', 'boolean');
        $connection->exec($definition_script);

        self::$db_config_file = dirname(__DIR__, 5) . '/Fixtures/' . uniqid('db-config-test-', false) . '.json';
        file_put_contents(self::$db_config_file, json_encode(self::$conn->getConnectionParams()));

        self::$storage = new DatabaseRecordStorage(self::$db_config_file);
    }

    public static function tearDownAfterClass()
    {
        unlink(self::$db_config_file);
    }

    public function testAppendingEntryRecordShouldInsertEntryRecordInDatabase()
    {
        self::$storage->appendEntryRecord($this->entry_record);
        self::$storage->finishInsertion();

        self::$storage->loopEntryRecords(function (EntryRecord $record, array $params, $return_type) {
            self::assertEquals($this->entry_record, $record);
            self::assertEquals($this->params, $params);
            self::assertNull($return_type);
        });
    }

    public function testAppendReturnRecordShouldReturnTheReturnRecordWithEntryRecord()
    {
        self::$storage->appendEntryRecord($this->entry_record);
        self::$storage->appendReturnRecord($this->return_record);
        self::$storage->finishInsertion();

        self::$storage->loopEntryRecords(function (EntryRecord $record, array $params, $return_type) {
            self::assertEquals($this->entry_record, $record);
            self::assertEquals($this->return_record->getReturnType(), $return_type);
        });
    }

    public function testWhenEntryRecordBatchLimitIsReachedThenInsertIntoDatabase()
    {
        for ($i = 0; $i < DatabaseRecordStorage::RECORDS_PER_BATCH; $i++) {
            self::$storage->appendEntryRecord(new EntryRecord($i, 'function' . $i, true, 'file.php', []));
        }

        self::$storage->finishInsertion();
        self::$storage->loopEntryRecords(function () use (&$count) {
            $count++;
        });

        self::assertSame(DatabaseRecordStorage::RECORDS_PER_BATCH, $count);
    }

    public function testClearRecordsShouldStopTransactionAndEmptyTheDatabase()
    {
        self::$storage->appendEntryRecord($this->entry_record);
        self::$storage->appendReturnRecord($this->return_record);
        self::$storage->clearRecords();

        $count = 0;
        self::$storage->loopEntryRecords(function () use (&$count) {
            $count++;
        });

        self::assertSame(0, $count);
    }

    public function testWhenNoDatabaseConfigFoundThenThrowError()
    {
        $this->expectException(\InvalidArgumentException::class);
        new DatabaseRecordStorage('some invalid path');
    }

    public function testWhenNotStartedTransactionIsCommittedThereShouldBeNoTransactionActive()
    {
        self::assertFalse(self::$storage->commitTransaction());
    }
}
