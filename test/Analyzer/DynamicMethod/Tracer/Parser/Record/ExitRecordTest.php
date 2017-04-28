<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ExitRecord
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\AbstractRecord
 */
class ExitRecordTest extends TestCase
{
    public function testExitRecordHasCorrectProperties()
    {
        $file_name   = 'SomeFile.php';
        $function_nr = 1;
        $entry       = new ExitRecord($function_nr);
        $entry->setFunctionDeclarationFile($file_name);

        self::assertInstanceOf(AbstractRecord::class, $entry);
        self::assertSame($function_nr, $entry->getNumber());
        self::assertSame($file_name, $entry->getFunctionDeclarationFile());
    }
}
