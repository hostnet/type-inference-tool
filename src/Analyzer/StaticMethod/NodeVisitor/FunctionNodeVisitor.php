<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Mapper\TracerPhpTypeMapper;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor used to collect classes, methods, extends and implements.
 */
class FunctionNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var AnalyzedFunctionCollection
     */
    private $analyzed_function_collection;

    /**
     * @var string
     */
    private $file_path;

    /**
     * @var AnalyzedClass
     */
    private $current_class;

    /**
     * @var AnalyzedFunction[]
     */
    private $analyzed_functions = [];

    /**
     * Current position in the list of AnalyzedFunctions.
     *
     * @var int
     */
    private $current_function = -1;

    /**
     * @param AnalyzedFunctionCollection $analyzed_function_collection
     * @param string $file_path
     */
    public function __construct(AnalyzedFunctionCollection $analyzed_function_collection, string $file_path)
    {
        $this->analyzed_function_collection = $analyzed_function_collection;
        $this->file_path                    = $file_path;
        $this->current_class                = new AnalyzedClass();
    }

    /**
     * {@inheritdoc}
     */
    public function beforeTraverse(array $nodes)
    {
        parent::beforeTraverse($nodes);
        $this->current_class->setFullPath($this->file_path);
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        parent::enterNode($node);
        $this->handleNamespaceNode($node);
        $this->handleClassOrInterfaceNode($node);
        $this->handleClassMethodNode($node);
    }

    /**
     * {@inheritdoc}
     */
    public function afterTraverse(array $nodes)
    {
        parent::afterTraverse($nodes);
        $this->analyzed_function_collection->addAll($this->analyzed_functions);
    }

    /**
     * Sets the namespace of the current class.
     *
     * @param Node $node
     */
    private function handleNamespaceNode(Node $node)
    {
        if ($node instanceof Namespace_) {
            $this->current_class->setNamespace($node->name->toString());
        }
    }

    /**
     * Creates a new AnalyzedFunction in case of a ClassMethod node.
     *
     * @param Node $node
     */
    private function handleClassMethodNode(Node $node)
    {
        if (!$node instanceof ClassMethod) {
            return;
        }

        $has_return_type = $node->returnType !== null;
        if ($has_return_type && $node->returnType instanceof FullyQualified) {
            $return_type = $node->returnType->toString();
        } else {
            $return_type = $node->returnType;
        }

        $this->current_function++;
        $this->analyzed_functions[$this->current_function] = new AnalyzedFunction(
            $this->current_class,
            $node->name,
            $return_type,
            $has_return_type
        );
    }

    /**
     * Sets the class name of the currently analysed class. Also checks for extends
     * or implements.
     *
     * @param Node $node
     */
    private function handleClassOrInterfaceNode(Node $node)
    {
        if (!$node instanceof Class_ && !$node instanceof Interface_) {
            return;
        }

        $this->current_class->setClassName($node->name);

        if ($node->extends instanceof Name || (is_array($node->extends) && count($node->extends) > 0)) {
            $extended_class = is_array($node->extends) ? implode('\\', $node->extends) : $node->extends->toString();

            list($namespace, $class_name) = TracerPhpTypeMapper::extractTraceFunctionName($extended_class);
            $this->current_class->setExtends(new AnalyzedClass($namespace, $class_name));
        }

        if (in_array('implements', $node->getSubNodeNames(), true) && count($node->implements) > 0) {
            $this->current_class->setImplements(array_map(function (Name $interface) {
                list($namespace, $class_name) = TracerPhpTypeMapper::extractTraceFunctionName($interface->toString());
                return new AnalyzedClass($namespace, $class_name);
            }, $node->implements));
        }
    }
}
