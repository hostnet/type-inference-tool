<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

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
