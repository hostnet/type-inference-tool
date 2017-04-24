<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

/**
 * Holds analysed data used to retrieve whether a function class overrides
 * another classes.
 */
class AnalyzedClass
{
    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $class_name;

    /**
     * @var AnalyzedClass
     */
    private $extends;

    /**
     * @var AnalyzedClass[]
     */
    private $implements;

    /**
     * @var string[]
     */
    private $methods;

    /**
     * @var string
     */
    private $full_path;

    /**
     * @param string $namespace
     * @param string $class_name
     * @param string $full_path
     * @param AnalyzedClass $extends
     * @param AnalyzedClass[] $implements
     * @param string[] $methods
     */
    public function __construct(
        string $namespace,
        string $class_name,
        string $full_path,
        AnalyzedClass $extends = null,
        array $implements,
        array $methods = []
    ) {
        $this->namespace  = $namespace;
        $this->class_name = $class_name;
        $this->full_path  = $full_path;
        $this->extends    = $extends;
        $this->implements = $implements;
        $this->methods    = $methods;
    }

    /**
     * Returns a list with all the types a given class inherits.
     *
     * @param AnalyzedClass $class
     * @param AnalyzedClass[] $parents
     * @return AnalyzedClass[]
     */
    public function getParents(AnalyzedClass $class = null, array $parents = []): array
    {
        $class = $class ?? $this;

        if (!in_array($class, $parents, true)) {
            $parents[] = $class;
        }

        if ($class->getExtends() !== null) {
            $parents = array_merge($this->getParents($class->getExtends(), $parents));
        }

        foreach ($class->getImplements() as $implement) {
            $parents = array_merge($this->getParents($implement, $parents));
        }

        return $parents;
    }

    /**
     * Takes two arrays containing AnalyzedClasses and returns an array with
     * the matching entries.
     *
     * @param AnalyzedClass[] $types
     * @param AnalyzedClass[] $compare_to_types
     * @return AnalyzedClass[]
     */
    public static function matchAnalyzedClasses(array $types, array $compare_to_types): array
    {
        $matches = [];
        foreach ($types as $type) {
            foreach ($compare_to_types as $compare_to_type) {
                if ($type->getFqcn() === $compare_to_type->getFqcn()) {
                    $matches[] = $type;
                }
            }
        }
        return $matches;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->class_name;
    }

    /**
     * @return AnalyzedClass
     */
    public function getExtends()
    {
        return $this->extends;
    }

    /**
     * @return AnalyzedClass[]
     */
    public function getImplements(): array
    {
        return $this->implements;
    }

    /**
     * @return string
     */
    public function getFqcn(): string
    {
        return $this->namespace . '\\' . $this->class_name;
    }

    /**
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return string
     */
    public function getFullPath(): string
    {
        return $this->full_path;
    }

    /**
     * Appends a function to the list with methods, if not present.
     *
     * @param string $function_name
     */
    public function addMethod(string $function_name)
    {
        if (!in_array($function_name, $this->methods, true)) {
            $this->methods[] = $function_name;
        }
    }
}
