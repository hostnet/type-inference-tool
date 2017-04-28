<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data\Type;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;

/**
 * Represents a PHP non-scalar type.
 */
final class NonScalarPhpType extends AnalyzedClass implements PhpTypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->getFqcn();
    }

    /**
     * Takes an array of PhpTypeInterface and determines the common parent
     * between all those PHP-types.
     *
     * @param PhpTypeInterface[] $returns
     * @return PhpTypeInterface
     * @throws \InvalidArgumentException
     */
    public static function getCommonParent(array $returns): PhpTypeInterface
    {
        $all_parent_types = [];

        foreach ($returns as $return) {
            if ($return instanceof AnalyzedClass) {
                $all_parent_types[] = $return->getParents();
            }
        }

        $common_types = array_reduce($all_parent_types, function ($reduced, $current) {
            if ($reduced === null) {
                return $current;
            }
            return AnalyzedClass::matchAnalyzedClasses($reduced, $current);
        });

        if (count($common_types) === 1) {
            return NonScalarPhpType::fromAnalyzedClass($common_types[0]);
        }

        return new UnresolvablePhpType(UnresolvablePhpType::INCONSISTENT);
    }

    /**
     * Converts a {@link AnalyzedClass} to a {@link NonScalarPhpType}.
     *
     * @param AnalyzedClass $analyzed_class
     * @return NonScalarPhpType
     */
    public static function fromAnalyzedClass(AnalyzedClass $analyzed_class): NonScalarPhpType
    {
        return new NonScalarPhpType(
            $analyzed_class->getNamespace(),
            $analyzed_class->getClassName(),
            $analyzed_class->getFullPath(),
            $analyzed_class->getExtends(),
            $analyzed_class->getImplements(),
            $analyzed_class->getMethods()
        );
    }
}
