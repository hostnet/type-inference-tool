<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

/**
 * Holds the string-representation of a valid PHP type.
 */
class PhpType
{
    const INCONSISTENT = 'inconsistent';
    const NONE         = 'none';

    /**
     * @var string
     */
    private $type;

    /**
     * @param string $type
     */
    public function __construct($type)
    {
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
