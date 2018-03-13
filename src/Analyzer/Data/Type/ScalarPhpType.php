<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data\Type;

/**
 * Represents a scalar PHP-type.
 */
final class ScalarPhpType implements PhpTypeInterface
{
    const TYPE_INT     = 'int';
    const TYPE_FLOAT   = 'float';
    const TYPE_STRING  = 'string';
    const TYPE_BOOL    = 'bool';
    const SCALAR_TYPES = [self::TYPE_INT, self::TYPE_FLOAT, self::TYPE_STRING, self::TYPE_BOOL];

    /**
     * @var string
     */
    private $type;

    /**
     * @var bool
     */
    private $is_nullable = false;

    /**
     * @param string $type
     * @param bool $is_nullable
     * @throws \InvalidArgumentException
     */
    public function __construct(string $type, bool $is_nullable = false)
    {
        if (!in_array($type, self::SCALAR_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf("Given type '%s' is not a PHP-scalar.", $type));
        }

        $this->is_nullable = $is_nullable;
        $this->type        = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->type;
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
}
