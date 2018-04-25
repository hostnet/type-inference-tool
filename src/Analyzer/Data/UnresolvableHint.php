<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data;

/**
 * This class describes either a return type declaration or type hint
 * that should not be added. UnresolvableHints are used after checking
 * whether ReturnTypeInstructions or TypeHintInstructions are valid.
 */
final class UnresolvableHint
{
    const HINT_TYPE_UNDEFINED = -1;
    const HINT_TYPE_PARAMETER = 0;
    const HINT_TYPE_RETURN    = 1;

    /**
     * Indication whether the UnresolvableHint applies to a parameter (0)
     * or to a return type (1).
     *
     * @var int
     */
    private $hint_type;

    /**
     * @var AnalyzedClass
     */
    private $class;

    /**
     * @var string
     */
    private $function_name;

    /**
     * @var int
     */
    private $argument_number;

    /**
     * @param AnalyzedClass $class
     * @param string $function_name
     * @param int $hint_type
     * @param int $argument_number
     */
    public function __construct(
        AnalyzedClass $class,
        string $function_name,
        int $hint_type = self::HINT_TYPE_UNDEFINED,
        int $argument_number = self::HINT_TYPE_UNDEFINED
    ) {
        $this->class           = $class;
        $this->function_name   = $function_name;
        $this->hint_type       = $hint_type;
        $this->argument_number = $argument_number;
    }

    /**
     * @return AnalyzedClass
     */
    public function getClass(): AnalyzedClass
    {
        return $this->class;
    }

    /**
     * @return string
     */
    public function getFunctionName(): string
    {
        return $this->function_name;
    }

    /**
     * @return int
     */
    public function getHintType(): int
    {
        return $this->hint_type;
    }

    /**
     * @return int
     */
    public function getArgumentNumber(): int
    {
        return $this->argument_number;
    }
}
