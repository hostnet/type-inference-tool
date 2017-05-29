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
}
