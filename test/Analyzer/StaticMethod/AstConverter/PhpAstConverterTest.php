<?php
declare(strict_types=1);
/**
 * @copyright 2017 Hostnet B.V.
 */

namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod\AstConverter;

use PhpParser\Node;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\StaticMethod\AstConverter\PhpAstConverter
 */
class PhpAstConverterTest extends TestCase
{
    public function testConvertPhpCodeShouldOutputAnAbstractSyntaxTree()
    {
        $php_ast_converter    = new PhpAstConverter();
        $abstract_syntax_tree = $php_ast_converter->convert('<?php $some_number = 123;');

        self::assertCount(1, $abstract_syntax_tree);
        self::assertInstanceOf(Node::class, $abstract_syntax_tree[0]);
    }
}
