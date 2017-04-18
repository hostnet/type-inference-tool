<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data;

/**
 * Holds a return type from an analyzed function.
 */
class AnalyzedReturn
{
    /**
     * @var PhpType
     */
    private $type;

    /**
     * @param PhpType $type
     */
    public function __construct(PhpType $type)
    {
        $this->type = $type;
    }

    /**
     * @return PhpType
     */
    public function getType(): PhpType
    {
        return $this->type;
    }
}
