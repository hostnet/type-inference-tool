<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor;

use gossi\docblock\Docblock;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedParameter;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Mapper\TracerPhpTypeMapper;
use Hostnet\Component\TypeInference\Analyzer\StaticMethod\StaticAnalyzer;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;

/**
 * Node visitor used to collect classes, methods, extends and implements.
 */
final class FunctionNodeVisitor extends AbstractAnalyzingNodeVisitor
{
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
     * @var string[] ['fqcn' => ['functions']]
     */
    private $function_index;

    /**
     * @param AnalyzedFunctionCollection $analyzed_function_collection
     * @param string $file_path
     * @param string[] $function_index
     */
    public function __construct(
        AnalyzedFunctionCollection $analyzed_function_collection,
        string $file_path,
        array &$function_index = []
    ) {
        parent::__construct($analyzed_function_collection);
        $this->file_path      = $file_path;
        $this->current_class  = new AnalyzedClass();
        $this->function_index = $function_index;
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
        $this->getAnalyzedFunctionCollection()->addAll($this->analyzed_functions);
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

        $return_type = $this->getClassMethodReturnType($node);
        $this->current_function++;
        $this->analyzed_functions[$this->current_function] = new AnalyzedFunction(
            $this->current_class,
            $node->name,
            $return_type,
            $return_type !== null,
            $this->getClassMethodParameters($node)
        );

        if ($node->getDocComment() !== null) {
            $docblock = new Docblock($node->getDocComment()->getText());
            $this->analyzed_functions[$this->current_function]->setDocblock($docblock);
        }
    }

    /**
     * Sets the class name of the currently analysed class. Also checks for extends
     * or implements.
     *
     * @param Node $node
     */
    private function handleClassOrInterfaceNode(Node $node)
    {
        if (!$node instanceof Class_ && !$node instanceof Interface_ && !$node instanceof Trait_) {
            return;
        }

        $this->current_class->setClassName($node->name);

        if ($node->extends instanceof Name || (is_array($node->extends) && count($node->extends) > 0)) {
            $extended_class = is_array($node->extends) ? implode('\\', $node->extends) : $node->extends->toString();

            list($namespace, $class_name) = TracerPhpTypeMapper::extractTraceFunctionName($extended_class);
            list($file_path, $functions)  = $this->listMethodsForChild($namespace, $class_name);

            $extended_class = new AnalyzedClass($namespace, $class_name, $file_path, null, [], $functions);
            $this->current_class->setExtends($extended_class);
        }

        if (in_array('implements', $node->getSubNodeNames(), true) && count($node->implements) > 0) {
            $this->current_class->setImplements(array_map(function (Name $interface) {
                list($namespace, $class_name) = TracerPhpTypeMapper::extractTraceFunctionName($interface->toString());
                list($file_path, $functions)  = $this->listMethodsForChild($namespace, $class_name);
                return new AnalyzedClass($namespace, $class_name, $file_path, null, [], $functions);
            }, $node->implements));
        }
    }

    /**
     * Returns all methods a class has, including methods inherited
     * from its parents.
     *
     * @param string $namespace
     * @param string $class_name
     * @return string[]
     */
    private function listMethodsForChild(string $namespace, string $class_name): array
    {
        $functions = [];
        $file_path = null;
        $fqcn      = $namespace . '\\' . $class_name;
        if (array_key_exists($fqcn, $this->function_index)) {
            $indexed_function =  $this->function_index[$fqcn];
            $file_path        = $indexed_function['path'];
            $functions        = StaticAnalyzer::listAllMethods($this->function_index, $fqcn);
        }
        return [$file_path, $functions];
    }

    /**
     * Used to retrieve the return type from a ClassMethod.
     *
     * @param ClassMethod $class
     * @return string|null
     */
    private function getClassMethodReturnType(ClassMethod $class): ?string
    {
        if ($class->returnType instanceof NullableType) {
            $type = $class->returnType->type;
            return $type instanceof Name ? $type->toString() : $type;
        }

        if (($class->returnType instanceof FullyQualified && $class->returnType !== null)
            || $class->returnType instanceof Name
        ) {
            return $class->returnType->toString();
        }

        return $class->returnType;
    }

    /**
     * Retrieves a list of AnalyzedParameters extracted from the given class
     * method.
     *
     * @param ClassMethod $class
     * @return AnalyzedParameter[]
     */
    private function getClassMethodParameters(ClassMethod $class): array
    {
        $analyzed_parameters = [];

        foreach ($class->params as $param) {
            $analyzed_parameter    = new AnalyzedParameter();
            $analyzed_parameters[] = $analyzed_parameter;

            $analyzed_parameter->setName($param->name);
            $analyzed_parameter->setHasDefaultValue($param->default !== null);

            if ($param->type === null) {
                continue;
            }

            $analyzed_parameter->setHasTypeHint(true);
            if ($param->type instanceof Name) {
                $analyzed_parameter->setType($param->type->toString());
                continue;
            }

            if ($param->type instanceof NullableType) {
                $type = $param->type->type;
                $analyzed_parameter->setType($type instanceof Name ? $type->toString() : $type);
                continue;
            }

            $analyzed_parameter->setType($param->type);
        }

        return $analyzed_parameters;
    }
}
