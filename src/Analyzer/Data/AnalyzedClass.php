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
     * @param string $namespace
     * @param string $class_name
     * @param AnalyzedClass $extends
     * @param AnalyzedClass[] $implements
     * @param string[] $methods
     */
    public function __construct(
        string $namespace,
        string $class_name,
        AnalyzedClass $extends,
        array $implements,
        array $methods
    ) {
        $this->namespace  = $namespace;
        $this->class_name = $class_name;
        $this->extends    = $extends;
        $this->implements = $implements;
        $this->methods    = $methods;
    }

    /**
     * Returns a list with all the types a given class inherits.
     *
     * @param AnalyzedClass $class
     * @param string[] $parents
     * @return string[]
     */
    public function getParents(AnalyzedClass $class, array $parents = []): array
    {
        if (!in_array($class->getClassName(), $parents, true)) {
            $parents[] = $class->getClassName();
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
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @param AnalyzedClass $extends
     */
    public function setExtends(AnalyzedClass $extends)
    {
        $this->extends = $extends;
    }

    /**
     * @return string
     */
    public function getFqcn(): string
    {
        return $this->namespace . '\\' . $this->class_name;
    }
}
