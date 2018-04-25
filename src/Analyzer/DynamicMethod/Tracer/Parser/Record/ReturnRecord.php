<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

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
    private $return_type;

    /**
     * @param int $number
     * @param string $return_type
     */
    public function __construct(int $number, string $return_type)
    {
        parent::__construct($number);
        $this->return_type = $return_type;
    }

    /**
     * @return string
     */
    public function getReturnType(): string
    {
        return $this->return_type;
    }
}
