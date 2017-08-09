<?php
declare(strict_types=1);
/**
 * @copyright 2017 Hostnet B.V.
 */

namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor;

use gossi\docblock\Docblock;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedParameter;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\UnresolvablePhpType;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor\DocblockNodeVisitor
 * @covers \Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor\AbstractAnalyzingNodeVisitor
 */
class DocblockNodeVisitorTest extends TestCase
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
        $this->node_visitor = new DocblockNodeVisitor(
            $this->collection,
            (new Finder())->in(dirname(__DIR__, 3) . '/Fixtures/ExampleStaticAnalysis/Example-Project/')
        );

        $this->collection->add(new AnalyzedFunction(
            new AnalyzedClass('Just\Some\NamespaceName', 'SomeClass', '/path/SomeFile.php', null, [], ['foobar']),
            'foobar',
            null,
            false,
            [new AnalyzedParameter('arg0')]
        ));
    }

    public function testWhenDocBlockContainsParamAndReturnHintItShouldBeCollected()
    {
        $this->method_node->setDocComment(new Doc(<<<'php'
/**
 * Some kind of description.
 *
 * @param bool $arg0 Parameter description
 * @return string Return description
 */
php
        ));

        $this->traverseTree();
        $results = $this->collection->getAll();

        self::assertCount(1, $results);
        self::assertSame(ScalarPhpType::TYPE_STRING, $results[0]->getCollectedReturns()[0]->getType()->getName());
        self::assertSame(
            ScalarPhpType::TYPE_BOOL,
            $results[0]->getCollectedArguments()[0]->getArguments()[0]->getName()
        );
    }

    public function testWhenDocBlockDoesNotContainParamAndReturnHintThenNothingShouldBeAnalyzed()
    {
        $this->traverseTree();
        $results = $this->collection->getAll();

        self::assertCount(1, $results);
        self::assertEmpty($results[0]->getCollectedArguments());
        self::assertEmpty($results[0]->getCollectedReturns());
    }

    public function testWhenDocBlockParamHasWrongNameItShouldNotBeAnalyzed()
    {
        $this->method_node->setDocComment(new Doc(<<<'php'
/**
 * Some kind of description.
 *
 * @param bool $non_existent_param_name Parameter description
 * @return string Return description
 */
php
        ));

        $this->traverseTree();
        $results = $this->collection->getAll();

        self::assertInstanceOf(UnresolvablePhpType::class, $results[0]->getCollectedArguments()[0]->getArguments()[0]);
        self::assertSame(
            UnresolvablePhpType::DOCBLOCK,
            $results[0]->getCollectedArguments()[0]->getArguments()[0]->getName()
        );
    }

    public function testWhenMethodHasDocblockWithParamHintsTheObjectNamespacesShouldBeResolved()
    {
        $fqcn = 'Hostnet\Component\SomeComponent\SomeClass';

        $this->namespace_node = new Namespace_(new Name(['Just', 'Some', 'NamespaceName']), [
            new Use_([new UseUse(new Name($fqcn), 'SomeClass', 0)], 1),
            new Use_([new UseUse(new Name('Library\SomeUtilClass'), 'SomeTestClass', 0)], 1),
            $this->class_node
        ], []);

        $this->method_node->setDocComment(new Doc(<<<'php'
/**
 * Description
 *
 * @param SomeClass $arg0 This parameter doesn't exist
 */
php
        ));

        $this->traverseTree();
        $results = $this->collection->getAll();

        self::assertCount(1, $results);
        self::assertCount(1, $results[0]->getCollectedArguments());
        self::assertCount(1, $results[0]->getCollectedArguments()[0]->getArguments());
        self::assertSame($fqcn, $results[0]->getCollectedArguments()[0]->getArguments()[0]->getName());
    }

    /**
     * @dataProvider docBlockReturnTypeProvider
     */
    public function testDefinedReturnTypeFromDocBlockShouldBeAnalyzed(string $docblock, string $type, bool $is_nullable)
    {
        $this->method_node->setDocComment(new Doc($docblock));

        $this->traverseTree();
        $results       = $this->collection->getAll();
        $resulted_type = $results[0]->getCollectedReturns()[0]->getType();

        self::assertContains($type, $resulted_type->getName());
        self::assertEquals($is_nullable, $resulted_type->isNullable());
    }

    public function testWhenNotImportedClassIsHintedThenDoNotCallItGlobally()
    {
        $finder = (new Finder())->in(dirname(__DIR__, 3) . '/Fixtures/ExampleStaticAnalysis/Example-Project/');

        $this->collection->getAll()[0]->getClass()->setNamespace('ExampleStaticProject');
        $this->namespace_node = new Namespace_(new Name(['ExampleStaticProject']), [$this->class_node]);

        $this->method_node->setDocComment(new Doc('/** @return SomeClass */'));
        $this->node_visitor = new DocblockNodeVisitor($this->collection, $finder);

        $this->traverseTree();
        $results = $this->collection->getAll();

        self::assertSame('ExampleStaticProject\SomeClass', $results[0]->getCollectedReturns()[0]->getType()->getName());
    }

    public function testWhenDocBlockInheritsFromParentThenUseParentDocBlock()
    {
        $finder = (new Finder())->in(dirname(__DIR__, 3) . '/Fixtures/ExampleStaticAnalysis/Example-Project/');

        $this->collection = new AnalyzedFunctionCollection();
        $parent_class     = new AnalyzedClass('ExampleStaticProject', 'FooClassInterface', 'file.php', null, [], [
            'doFoobar'
        ]);
        $parent_function  = new AnalyzedFunction($parent_class, 'doFoobar', null, false, [
            new AnalyzedParameter('number')
        ]);
        $parent_function->setDocblock(new Docblock(<<<'php'
/**
 * This is a docblock.
 *
 * @param int $number Some input variable
 * @return bool
 */
php
        ));
        $this->collection->add($parent_function);

        $abstract_child_class      = new AnalyzedClass('ExampleStaticProject', 'AbstractSomeClass', 'file3.php', null, [
            $parent_class
        ], ['doFoobar']);
        $abstract_child_class_func = new AnalyzedFunction($abstract_child_class, 'doFoobar', null, false, [
            new AnalyzedParameter('number')
        ]);
        $abstract_child_class_func->setDocblock(new Docblock('/** @param $something_else blah blah. */'));
        $this->collection->add($abstract_child_class_func);

        $child_class = new AnalyzedClass('ExampleStaticProject', 'SomeClass', 'file3.php', $abstract_child_class, [], [
            'foobar', 'doFoobar'
        ]);
        $this->collection->add(new AnalyzedFunction($child_class, 'foobar', null, false, []));

        $do_foobar_function = new AnalyzedFunction($child_class, 'doFoobar', null, false, [
            new AnalyzedParameter('number')
        ]);
        $this->collection->add($do_foobar_function);

        $this->namespace_node = new Namespace_(new Name(['ExampleStaticProject']), [$this->class_node], []);
        $this->class_node     = new Class_('SomeClass', [
            'extends' => new Name(['AbstractSomeClass']),
            'implements' => [],
            'stmts' => [$this->method_node]
        ], []);
        $this->method_node    = new ClassMethod('doFoobar', [
            'params' => [new Param('number', new LNumber(5))],
            'type' => 1,
            'stmts' => [$this->return_node]
        ], []);

        $this->method_node->setDocComment(new Doc('/** I inherit from my parent */'));
        $this->node_visitor = new DocblockNodeVisitor($this->collection, $finder);

        $this->traverseTree();
        $results = $this->collection->getAll();

        self::assertContains($do_foobar_function, $results);
        self::assertSame(ScalarPhpType::TYPE_BOOL, $do_foobar_function->getCollectedReturns()[0]->getType()->getName());
        self::assertSame(
            ScalarPhpType::TYPE_INT,
            $do_foobar_function->getCollectedArguments()[0]->getArguments()[0]->getName()
        );
    }

    public function testWhenParentHasNoDocIsShouldNotBeAnalyzed()
    {
        $collection = new AnalyzedFunctionCollection();

        $parent_class    = new AnalyzedClass('ExampleStaticProject', 'ParentClass', 'file.php', null, [], ['foobar']);
        $parent_function = new AnalyzedFunction($parent_class, 'foobar', null, false, [new AnalyzedParameter('arg')]);
        $collection->add($parent_function);

        $child_class    = new AnalyzedClass('ExampleStaticProject', 'ChildClass', '', $parent_class, [], ['foobar']);
        $child_function = new AnalyzedFunction($child_class, 'foobar', null, false, [new AnalyzedParameter('arg')]);
        $collection->add($child_function);

        $this->method_node = new ClassMethod('foobar', [
            'params' => [new Param('arg', new LNumber(5))],
            'type' => 1,
            'stmts' => [$this->return_node]
        ], []);
        $this->class_node  = new Class_('ChildClass', [
            'extends' => new Name(['ParentClass']),
            'implements' => [],
            'stmts' => [$this->method_node]
        ], []);

        $this->namespace_node = new Namespace_(new Name(['ExampleStaticProject']), [$this->class_node], []);
        $this->method_node->setDocComment(new Doc('/** See my parent */'));
        $finder = (new Finder())->in(dirname(__DIR__, 3) . '/Fixtures/ExampleStaticAnalysis/Example-Project/');

        $this->node_visitor = new DocblockNodeVisitor($collection, $finder);
        $this->traverseTree();
        $results = $collection->getAll();

        self::assertEmpty($results[0]->getCollectedReturns());
        self::assertEmpty($results[0]->getCollectedArguments());
        self::assertEmpty($results[1]->getCollectedReturns());
        self::assertEmpty($results[1]->getCollectedArguments());
    }

    public function testWhenDocDefinedObjectButItDoesNotExistThenDoNotRetrieveParentDoc()
    {
        $doc_block = "/**\n * @return SomeObject\n */";

        $some_class_parent = new AnalyzedClass('Just\Some\Ns', 'SomeClassParent', null, null, [], ['foobar']);
        $some_class        = new AnalyzedClass('Just\Some\Ns', 'SomeClass', null, $some_class_parent, [], ['foobar']);
        $foobar_method     = new AnalyzedFunction($some_class, 'foobar', ScalarPhpType::TYPE_STRING, true, []);
        $foobar_method->setDocblock(new Docblock($doc_block));

        $this->collection = new AnalyzedFunctionCollection();
        $this->collection->add($foobar_method);

        $this->node_visitor = new DocblockNodeVisitor(
            $this->collection,
            (new Finder())->in(dirname(__DIR__, 3) . '/Fixtures/ExampleStaticAnalysis/Example-Project/')
        );

        $this->return_node    = new Return_(new String_('Hello'));
        $this->method_node    = new ClassMethod('foobar', [
            'params' => [new Param('arg0', new ConstFetch(new Name('true')), 'bool')],
            'returnType' => 'string',
            'type' => 1,
            'stmts' => [$this->return_node],
            'attributes' => [
                'comments' => [new Doc('/** This is a doc block. */')]
            ]
        ], []);
        $this->class_node     = new Class_('SomeClass', [
            'extends' => 'SomeClassParent',
            'implements' => null,
            'stmts' => [$this->method_node]
        ], []);
        $this->namespace_node = new Namespace_(new Name(['Just', 'Some', 'Ns']), [$this->class_node], []);

        $this->method_node->setDocComment(new Doc($doc_block));
        $this->abstract_syntax_tree = [$this->namespace_node];
        $this->traverseTree();

        self::assertSame($doc_block, $this->collection->getAll()[0]->getDocblock()->toString());
    }

    /**
     * @param string $docblock
     * @dataProvider invalidSyntaxDocBlockProvider
     */
    public function testWhenDocContainsInvalidReturnSyntaxThenDoNotAnalyse(string $docblock)
    {
        $finder = (new Finder())->in(dirname(__DIR__, 3) . '/Fixtures/ExampleStaticAnalysis/Example-Project/');

        $this->collection->getAll()[0]->getClass()->setNamespace('ExampleStaticProject');
        $this->namespace_node = new Namespace_(new Name(['ExampleStaticProject']), [$this->class_node]);

        $this->method_node->setDocComment(new Doc($docblock));
        $this->node_visitor = new DocblockNodeVisitor($this->collection, $finder);

        $this->traverseTree();
        $results = $this->collection->getAll();

        self::assertEmpty($results[0]->getCollectedReturns());
    }

    public function invalidSyntaxDocBlockProvider(): array
    {
        return [
            ['/** @return - */'],
            ['/** @param invalid - */']
        ];
    }

    public function docBlockReturnTypeProvider(): array
    {
        return [
            ['/** @return string */', 'string', false],
            ['/** @return float */', 'float', false],
            ['/** @return int */', 'int', false],
            ['/** @return bool */', 'bool', false],
            ['/** @return $this */', 'Just\Some\NamespaceName\SomeClass', false],
            ['/** @return mixed */', UnresolvablePhpType::DOCBLOCK_MULTIPLE, false],
            ['/** @return string|float */', UnresolvablePhpType::DOCBLOCK_MULTIPLE, false],
            ['/** @return bool[] */', '\array', false],
            ['/** @return callable */', '\callable', false],
            ['/** @return null|\DateInterval */', '\DateInterval', true],
            ['/** @return \DateInterval|null */', '\DateInterval', true],
            ['/** @return \DateInterval|null|string */', UnresolvablePhpType::DOCBLOCK_MULTIPLE, false]
        ];
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
        $this->return_node = new Return_(new String_('Hello'));
        $this->method_node = new ClassMethod('foobar', [
            'params' => [new Param('arg0', new ConstFetch(new Name('true')), 'bool')],
            'returnType' => 'string',
            'type' => 1,
            'stmts' => [$this->return_node]
        ], []);

        $this->class_node     = new Class_('SomeClass', [
            'extends' => null,
            'implements' => null,
            'stmts' => [$this->method_node]
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
