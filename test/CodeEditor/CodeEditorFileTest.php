<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\CodeEditor;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\CodeEditor\CodeEditorFile
 */
class CodeEditorFileTest extends TestCase
{
    public function testFileContainsNewData()
    {
        $path     = 'Just\\Some\\Path\\File.txt';
        $contents = 'Contents of the file';
        $file     = new CodeEditorFile($path, $contents);

        self::assertSame($path, $file->getPath());
        self::assertSame($contents, $file->getContents());

        $new_contents = 'New contents';
        $file->setContents($new_contents);

        self::assertSame($new_contents, $file->getContents());
    }
}
