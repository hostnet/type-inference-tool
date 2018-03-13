<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\AbstractRecord
 */
class ReturnRecordTest extends TestCase
{
    public function testReturnRecordHasCorrectProperties()
    {
        $function_nr = 1;
        $return_type = 'string(10)';
        $file        = 'file.php';

        $entry = new ReturnRecord($function_nr, $return_type);

        self::assertInstanceOf(AbstractRecord::class, $entry);
        self::assertSame($function_nr, $entry->getNumber());
        self::assertSame($return_type, $entry->getReturnType());

        self::assertNull($entry->getFunctionDeclarationFile());
        $entry->setFunctionDeclarationFile($file);
        self::assertSame($file, $entry->getFunctionDeclarationFile());
    }
}
