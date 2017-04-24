<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record;

/**
 * Represents a return record in a function trace. A return record contains
 * the type that a function has returned.
 */
final class ReturnRecord extends AbstractRecord
{
    /**
     * @var string
     */
    private $return_value;

    /**
     * @param int $function_nr
     * @param string $return_value
     */
    public function __construct(int $function_nr, string $return_value)
    {
        parent::__construct($function_nr);
        $this->return_value = $return_value;
    }

    /**
     * @return string
     */
    public function getReturnValue(): string
    {
        return $this->return_value;
    }
}
