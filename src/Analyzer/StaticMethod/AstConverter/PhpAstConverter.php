<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod\AstConverter;

use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Class used to convert PHP-code to abstract syntax tree's.
 */
class PhpAstConverter
{
    /**
     * @var Parser
     */
    private $php_parser;

    /**
     * Initialises a PHP 7 AST parser.
     */
    public function __construct()
    {
        $parser_factory   = new ParserFactory();
        $this->php_parser = $parser_factory->create(ParserFactory::PREFER_PHP7);
    }

    /**
     * Returns an AST based on the given source code.
     *
     * @param string $php_code
     * @return Node[]
     */
    public function convert(string $php_code): array
    {
        return $this->php_parser->parse($php_code);
    }
}
