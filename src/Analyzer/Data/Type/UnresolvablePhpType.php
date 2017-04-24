<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data\Type;

/**
 * Represents a PHP-type that could not be inferred.
 */
final class UnresolvablePhpType implements PhpTypeInterface
{
    /**
     * Used in case of invalid use of mixed types.
     */
    const INCONSISTENT = 'inconsistent';

    /**
     * Used in case of optional parameter or no return statements.
     */
    const NONE = 'none';

    /**
     * @var string
     */
    private $type;

    /**
     * @param string $type
     * @throws \InvalidArgumentException
     */
    public function __construct(string $type)
    {
        if (!in_array($type, [self::INCONSISTENT, self::NONE], true)) {
            throw new \InvalidArgumentException('Invalid type given');
        }

        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->type;
    }
}
