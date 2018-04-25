<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data\Type;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;

/**
 * Represents a PHP non-scalar type.
 */
final class NonScalarPhpType extends AnalyzedClass implements PhpTypeInterface
{
    /**
     * @var bool
     */
    private $is_nullable = false;

    /**
     * @param string $namespace
     * @param string $class_name
     * @param string $full_path
     * @param AnalyzedClass $extends
     * @param array $implements
     * @param array $methods
     * @param bool $is_nullable
     */
    public function __construct(
        ?string $namespace = null,
        ?string $class_name = null,
        ?string $full_path = null,
        ?AnalyzedClass $extends = null,
        array $implements = [],
        array $methods = [],
        bool $is_nullable = false
    ) {
        parent::__construct($namespace, $class_name, $full_path, $extends, $implements, $methods);
        $this->is_nullable = $is_nullable;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->getFqcn();
    }

    /**
     * {@inheritdoc}
     */
    public function isNullable(): bool
    {
        return $this->is_nullable;
    }

    /**
     * {@inheritdoc}
     */
    public function setNullable(bool $is_nullable)
    {
        $this->is_nullable = $is_nullable;
    }

    /**
     * Takes an array of PhpTypeInterface and determines the common parent
     * between all those PHP-types.
     *
     * @param PhpTypeInterface[] $returns
     * @param bool $is_nullable
     * @return PhpTypeInterface
     * @throws \InvalidArgumentException
     */
    public static function getCommonParent(array $returns, bool $is_nullable = false): PhpTypeInterface
    {
        $all_parent_types    = [];
        $contains_scalar     = false;
        $contains_non_scalar = false;

        foreach ($returns as $return) {
            if ($return instanceof AnalyzedClass) {
                $all_parent_types[] = $return->getParents();
            }

            if ($return instanceof NonScalarPhpType) {
                $contains_non_scalar = true;
            }
            if ($return instanceof ScalarPhpType) {
                $contains_scalar = true;
            }

            if (($contains_non_scalar && $contains_scalar) || $return instanceof UnresolvablePhpType) {
                return new UnresolvablePhpType(UnresolvablePhpType::INCONSISTENT);
            }
        }

        $common_types = array_reduce($all_parent_types, function ($reduced, $current) {
            if ($reduced === null) {
                return $current;
            }
            return AnalyzedClass::matchAnalyzedClasses($reduced, $current);
        });

        if ($common_types !== null && count($common_types) === 1) {
            return self::fromAnalyzedClass($common_types[0], $is_nullable);
        }

        return new UnresolvablePhpType(UnresolvablePhpType::INCONSISTENT);
    }

    /**
     * Converts a {@link AnalyzedClass} to a {@link NonScalarPhpType}.
     *
     * @param AnalyzedClass $analyzed_class
     * @param bool $is_nullable
     * @return NonScalarPhpType
     */
    public static function fromAnalyzedClass(AnalyzedClass $analyzed_class, bool $is_nullable = false): NonScalarPhpType
    {
        return new NonScalarPhpType(
            $analyzed_class->getNamespace(),
            $analyzed_class->getClassName(),
            $analyzed_class->getFullPath(),
            $analyzed_class->getExtends(),
            $analyzed_class->getImplements(),
            $analyzed_class->getMethods(),
            $is_nullable
        );
    }
}
