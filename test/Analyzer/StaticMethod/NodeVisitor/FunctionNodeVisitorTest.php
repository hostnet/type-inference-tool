<?php
declare(strict_types=1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedParameter;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor\FunctionNodeVisitor
 */
class FunctionNodeVisitorTest extends TestCase
{
    /**
     * @var Node[]
     */
    private $abstract_syntax_tree;

    /**
     * @var Return_
     */
    private $return_node;

    /**
     * @var ClassMethod
     */
    private $method_node;

    /**
     * @var Class_
     */
    private $class_node;

    /**
     * @var Namespace_
     */
    private $namespace_node;

    /**
     * @var AnalyzedFunctionCollection
     */
    private $collection;

    /**
     * @var string
     */
    private $file;

    /**
     * @var FunctionNodeVisitor
     */
    private $node_visitor;

    protected function setUp()
    {
        $this->createAbstractSyntaxTree();
        $this->file         = '/path/SomeFile.php';
        $this->collection   = new AnalyzedFunctionCollection();
        $this->node_visitor = new FunctionNodeVisitor($this->collection, $this->file);
    }

    public function testBeforeTraverseShouldSetTheFileNameAndAddAnalyzedFunctionToCollection()
    {
        $this->traverseTree();

        $results           = $this->collection->getAll();
        $expected_function = new AnalyzedFunction(
            new AnalyzedClass(
                'Just\Some\NamespaceName',
                'SomeClass',
                $this->file,
                new AnalyzedClass('', '\AbstractSomeClass'),
                [new AnalyzedClass('', '\SomeClassInterface')],
                ['foobar']
            ),
            'foobar',
            'string',
            true,
            [
                new AnalyzedParameter('bool', true, true)
            ]
        );

        self::assertCount(1, $results);
        self::assertEquals($expected_function, $results[0]);
    }

    public function testTraversalAfterNameResolverShouldUseFullyQualifiedClassNames()
    {
        $this->method_node->returnType = new FullyQualified(new Name(['Namespace', 'Object']));

        $this->node_visitor->beforeTraverse($this->abstract_syntax_tree);
        $this->traverseTree();

        $results = $this->collection->getAll();

        self::assertSame('Namespace\Object', $results[0]->getDefinedReturnType());
    }

    public function testWhenClassMethodHasDefaultParametersItShouldBeAnalyzed()
    {
        $this->method_node = new ClassMethod('foobar', [
            'params' => [
                new Param('arg0', new LNumber(66), new Name('int')),
                new Param('arg0', new Node\Scalar\DNumber(10.6)),
            ],
            'returnType' => 'string',
            'type' => 1,
            'stmts' => [
                $this->return_node
            ]
        ], []);

        $this->traverseTree();
        $results = $this->collection->getAll();

        self::assertTrue($results[0]->getDefinedParameters()[0]->hasTypeHint());
        self::assertSame('int', $results[0]->getDefinedParameters()[0]->getType());
        self::assertFalse($results[0]->getDefinedParameters()[1]->hasTypeHint());
        self::assertTrue($results[0]->getDefinedParameters()[1]->hasDefaultValue());
    }

    /**
     * Creates an abstract syntax tree representing the following PHP-code:
     *
     * <pre>
     *     namespace Just\Some\NamespaceName;
     *
     *     class SomeClass extends AbstractSomeClass implements SomeClassInterface
     *     {
     *         public function foobar(bool $arg0 = true): string
     *         {
     *             return 'Hello';
     *         }
     *     }
     * </pre>
     */
    private function createAbstractSyntaxTree()
    {
        $this->return_node    = new Return_(new String_('Hello'));
        $this->method_node    = new ClassMethod('foobar', [
            'params' => [
                new Param('arg0', new ConstFetch(new Name('true')), 'bool')
            ],
            'returnType' => 'string',
            'type' => 1,
            'stmts' => [
                $this->return_node
            ]
        ], []);
        $this->class_node     = new Class_('SomeClass', [
            'extends' => new Name(['AbstractSomeClass']),
            'implements' => [new Name(['SomeClassInterface'])],
            'stmts' => [
                $this->method_node
            ]
        ], []);
        $this->namespace_node = new Namespace_(new Name(['Just', 'Some', 'NamespaceName']), [$this->class_node], []);

        $this->abstract_syntax_tree = [$this->namespace_node];
    }

    private function traverseTree()
    {
        $this->node_visitor->beforeTraverse($this->abstract_syntax_tree);
        $this->node_visitor->enterNode($this->namespace_node);
        $this->node_visitor->enterNode($this->class_node);
        $this->node_visitor->enterNode($this->method_node);
        $this->node_visitor->enterNode($this->return_node);
        $this->node_visitor->afterTraverse($this->abstract_syntax_tree);
    }
}
