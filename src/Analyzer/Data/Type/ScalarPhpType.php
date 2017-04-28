<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
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
     * @param string $type
     * @throws \InvalidArgumentException
     */
    public function __construct(string $type)
    {
        if (!in_array($type, self::SCALAR_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf("Given type '%s' is not a PHP-scalar.", $type));
        }

        $this->type = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->type;
    }
}
