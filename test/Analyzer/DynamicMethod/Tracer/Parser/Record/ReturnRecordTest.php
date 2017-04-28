<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord
 */
class ReturnRecordTest extends TestCase
{
    public function testExitRecordHasCorrectProperties()
    {
        $function_nr = 1;
        $return_type = 'string(10)';

        $entry = new ReturnRecord($function_nr, $return_type);

        self::assertInstanceOf(AbstractRecord::class, $entry);
        self::assertSame($function_nr, $entry->getNumber());
        self::assertSame($return_type, $entry->getReturnValue());
    }
}
