<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor;

use gossi\docblock\Docblock;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedCall;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedReturn;
use Hostnet\Component\TypeInference\Analyzer\Data\Exception\EntryNotFoundException;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\PhpTypeInterface;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\UnresolvablePhpType;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Mapper\TracerPhpTypeMapper;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use Symfony\Component\Finder\Finder;

/**
 * Used to retrieve parameter- and return types from docblocks
 * so they get used during analysis.
 */
final class DocblockNodeVisitor extends AbstractAnalyzingNodeVisitor
{
    /**
     * @var string Namespace of the currently analyzed class
     */
    private $namespace;

    /**
     * @var string Class name of the currently analyzes class
     */
    private $class_name;

    /**
     * Holds the use-statements of the currently analyzed class.
     * Used to determine namespaces of objects.
     *
     * @var string[] [alias => fqcn]
     */
    private $use_statements = [];

    /**
     * @var string Function name of the currently analyzed class
     */
    private $function_name;

    /**
     * @var Finder
     */
    private $source_files;

    /**
     * @param AnalyzedFunctionCollection $analyzed_function_collection
     * @param Finder $source_files
     */
    public function __construct(AnalyzedFunctionCollection $analyzed_function_collection, Finder $source_files)
    {
        parent::__construct($analyzed_function_collection);
        $this->source_files = $source_files;
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_) {
            $this->class_name = $node->name;
            return;
        }

        if ($node instanceof Namespace_) {
            $this->namespace = $node->name->toString();
            $this->handleUseStatements($node);
            return;
        }

        if (!($node instanceof ClassMethod)) {
            return;
        }

        $this->function_name = $node->name;
        $this->analyseDocBlock($node);
    }

    /**
     * Checks whether the currently analyzed class has use-statements.
     * If so, these use statements will be used to determine whether objects
     * used in the class are imported and what their namespaces are.
     *
     * @param Namespace_ $node
     */
    private function handleUseStatements(Namespace_ $node)
    {
        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Use_) {
                continue;
            }

            foreach ($stmt->uses as $use_stmt) {
                $this->use_statements[$use_stmt->alias] = $use_stmt->name->toString();
            }
        }
    }

    /**
     * Adds analyzed data to the AnalyzedFunction of the currently analyzed
     * function by retrieving parameter- and return type hints from the
     * docblock.
     *
     * @param ClassMethod $class_method
     * @throws \InvalidArgumentException
     */
    private function analyseDocBlock(ClassMethod $class_method)
    {
        if ($class_method->getDocComment() === null) {
            return;
        }

        $docblock = new Docblock($class_method->getDocComment()->getText());

        $current_function         = $this->getCurrentlyAnalyzingFunction();
        $current_function_parents = $this->getAnalyzedFunctionCollection()->getFunctionParents($current_function);
        if (count($current_function_parents) > 0) {
            $docblock = $this->retrieveParentDocblock($docblock, $current_function_parents, $current_function);
        }

        $this->resolveDocblockReturnType($docblock);
        $this->resolveDocblockParamTypes($docblock);
    }

    /**
     * Takes a docblock, extracts the return type declaration, if present,
     * and adds it to the currently analyzed function. By doing so the
     * return type declaration from the docblock will be used during inference.
     *
     * @param Docblock $docblock
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws EntryNotFoundException
     */
    private function resolveDocblockReturnType(Docblock $docblock)
    {
        if (!$docblock->hasTag('return') || '' === $docblock->getTags('return')->get(0)->getType()) {
            return;
        }

        try {
            $php_type = $this->resolvePhpType($docblock->getTags('return')->get(0)->getType());
        } catch (\Throwable $e) {
            return;
        }

        $this->getCurrentlyAnalyzingFunction()->addCollectedReturn(new AnalyzedReturn($php_type));
    }

    /**
     * Takes a docblock, extract the parameters, and add the parameter hints
     * to the currently analyzed function. By doing so the parameter types from
     * the docblock will be used during inference.
     *
     * @param Docblock $docblock
     * @throws \InvalidArgumentException
     * @throws EntryNotFoundException
     * @throws \RuntimeException
     */
    private function resolveDocblockParamTypes(Docblock $docblock)
    {
        if (!$docblock->hasTag('param')) {
            return;
        }

        $docblock_types = [];

        foreach ($this->getCurrentlyAnalyzingFunction()->getDefinedParameters() as $i => $defined_param) {
            $docblock_types[$i] = new UnresolvablePhpType(UnresolvablePhpType::DOCBLOCK);

            foreach ($docblock->getTags('param') as $docblock_param) {
                if ($docblock_param->getVariable() === null || $docblock_param->getType() === null) {
                    continue;
                }

                if (!preg_match(sprintf('/&?\$%s/', $defined_param->getName()), $docblock_param->getVariable())) {
                    continue;
                }

                try {
                    $docblock_types[$i] = $this->resolvePhpType($docblock_param->getType());
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        $this->getCurrentlyAnalyzingFunction()->addCollectedArguments(new AnalyzedCall($docblock_types));
    }

    /**
     * Takes the docblock of an AnalyzedFunction, checks whether the function
     * inherits from parents, if so, appends the parameter- and return types
     * from the parent docblocks to the child. By doing so, child docblocks
     * contain params and returns from its parents.
     *
     * @param Docblock $child_docblock
     * @param AnalyzedClass[] $current_function_parents
     * @param AnalyzedFunction $current_function
     * @return Docblock
     * @throws EntryNotFoundException
     */
    private function retrieveParentDocblock(
        Docblock $child_docblock,
        array $current_function_parents,
        AnalyzedFunction $current_function
    ): Docblock {
        foreach ($current_function_parents as $parent) {
            try {
                $parent_function = $this
                    ->getAnalyzedFunctionCollection()
                    ->get($parent->getFqcn(), $current_function->getFunctionName());
            } catch (EntryNotFoundException $e) {
                continue;
            }

            $parent_doc = $parent_function->getDocblock();

            if ($parent_doc === null) {
                continue;
            }

            foreach ($parent_doc->getTags('param') as $param) {
                $child_docblock->appendTag($param);
            }

            if (!$parent_doc->hasTag('return')) {
                continue;
            }

            $child_docblock->appendTag($parent_doc->getTags('return')->get(0));
        }

        return $child_docblock;
    }

    /**
     * Takes a list with string representations of types and removes all nulls.
     *
     * @param array $types_with_null
     * @return array
     */
    private function filterNullTypes(array $types_with_null): array
    {
        $types_without_null = [];

        foreach ($types_with_null as $type) {
            if ('null' === $type) {
                continue;
            }

            $types_without_null[] = $type;
        }

        return $types_without_null;
    }

    /**
     * Determines the PhpTypeInterface of the given type name.
     *
     * @param string $type_name
     * @return PhpTypeInterface
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function resolvePhpType(string $type_name): PhpTypeInterface
    {
        $is_nullable = false;

        if (strpos($type_name, '|') !== false) {
            $type_names     = explode('|', $type_name);
            $filtered_types = $this->filterNullTypes($type_names);
            $is_nullable    = count($filtered_types) < count($type_names);

            if (count($filtered_types) >= 2) {
                return new UnresolvablePhpType(
                    UnresolvablePhpType::DOCBLOCK_MULTIPLE,
                    sprintf("docblock defined multiple types '%s'", $type_name)
                );
            }

            $type_name = $filtered_types[0];
        }

        if (in_array($type_name, ['mixed', 'unknown', 'unknown_type'], true)) {
            $type =  new UnresolvablePhpType(UnresolvablePhpType::DOCBLOCK_MULTIPLE, 'defined in docblock as mixed');
            $type->setNullable($is_nullable);
            return $type;
        }

        if (strpos($type_name, '[]') !== false) {
            $type =  new NonScalarPhpType(null, 'array');
            $type->setNullable($is_nullable);
            return $type;
        }

        if ('self' === $type_name || '$this' === $type_name) {
            $type =  TracerPhpTypeMapper::toPhpType($this->namespace . '\\' . $this->class_name);
            $type->setNullable($is_nullable);
            return $type;
        }

        if (array_key_exists($type_name, $this->use_statements)) {
            $type = TracerPhpTypeMapper::toPhpType($this->use_statements[$type_name]);
            $type->setNullable($is_nullable);
            return $type;
        }

        if ($this->source_files !== null) {
            foreach ($this->source_files as $file) {
                $class_regex_pattern = sprintf('/(class|interface) %s(\n|\s)/', preg_quote($type_name, '/'));
                if (preg_match($class_regex_pattern, $file->getContents()) === 1
                    && strpos($file->getContents(), sprintf('namespace %s;', $this->namespace)) !== false
                ) {
                    $type = TracerPhpTypeMapper::toPhpType($this->namespace . '\\' . $type_name);
                    $type->setNullable($is_nullable);
                    return $type;
                }
            }
        }

        $type = TracerPhpTypeMapper::toPhpType($type_name);
        $type->setNullable($is_nullable);
        return $type;
    }

    /***
     * Retrieves the AnalyzedFunction of the currently analyzed function
     * from the AnalyzedFunctionCollection. By doing so, already analysed
     * data can be used.
     *
     * @return AnalyzedFunction
     */
    private function getCurrentlyAnalyzingFunction(): AnalyzedFunction
    {
        return $this
            ->getAnalyzedFunctionCollection()
            ->get($this->namespace . '\\' . $this->class_name, $this->function_name);
    }
}
