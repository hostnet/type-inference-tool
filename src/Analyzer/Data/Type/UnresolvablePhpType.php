<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
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
     * Used in case of no type definition in a docblock.
     */
    const DOCBLOCK = 'docblock';

    /**
     * Used in case of mixed type hints in a docblock.
     * E.g. the use of 'mixed' or '|' ('string|null').
     */
    const DOCBLOCK_MULTIPLE = 'docblock_multiple';

    /**
     * @var string
     */
    private $type;

    /**
     * Optional message used to explain why a PhpType is unresolved.
     *
     * @var string
     */
    private $message;

    /**
     * @var bool
     */
    private $is_nullable = false;

    /**
     * @param string $type
     * @param string $message
     * @throws \InvalidArgumentException
     */
    public function __construct(string $type, string $message = null)
    {
        if (!in_array($type, [self::INCONSISTENT, self::NONE, self::DOCBLOCK, self::DOCBLOCK_MULTIPLE], true)) {
            throw new \InvalidArgumentException('Invalid type given');
        }

        $this->type    = $type;
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->type . ($this->message !== null ? ' (' . $this->message . ')' : '');
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->is_nullable;
    }

    /**
     * @param bool $is_nullable
     */
    public function setNullable(bool $is_nullable)
    {
        $this->is_nullable = $is_nullable;
    }

    /**
     * Returns whether the given PhpType is an unresolvable type with the type
     * 'none'. Used to determine whether a parameter or return type is
     * nullable.
     *
     * @param PhpTypeInterface $type
     * @return bool
     */
    public static function isPhpTypeNullable(PhpTypeInterface $type): bool
    {
        return $type instanceof UnresolvablePhpType && $type->getType() === self::NONE;
    }
}
