<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord
 */
class EntryRecordTest extends TestCase
{
    public function testEntryRecordHasCorrectProperties()
    {
        $function_nr     = 1;
        $function_name   = 'functionName';
        $is_user_defined = true;
        $file_name       = '/path/to/file.php';
        $parameters      = ['string(10)', 'array(5)'];

        $entry = new EntryRecord(
            $function_nr,
            $function_name,
            $is_user_defined,
            $file_name,
            $parameters
        );

        self::assertInstanceOf(AbstractRecord::class, $entry);
        self::assertSame($function_nr, $entry->getNumber());
        self::assertSame($function_name, $entry->getFunctionName());
        self::assertSame($is_user_defined, $entry->isUserDefined());
        self::assertSame($file_name, $entry->getFileName());
        self::assertSame($parameters, $entry->getParameters());
    }
}
