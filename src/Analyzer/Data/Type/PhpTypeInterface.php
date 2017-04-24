<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
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
}
