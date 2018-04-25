<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data\Type;

/**
 * Represents a PHP-type. This could either be a scalar or non-scalar type.
 */
interface PhpTypeInterface
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return bool
     */
    public function isNullable(): bool;

    /**
     * @param bool $is_nullable
     */
    public function setNullable(bool $is_nullable);
}
