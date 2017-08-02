<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record;

/**
 * Representation of a trace record.
 */
abstract class AbstractRecord
{
    /**
     * Unique number in a trace mapped to a function.
     *
     * @var int
     */
    private $number;

    /**
     * @var string
     */
    private $function_declaration_file;

    /**
     * @param int $number
     */
    public function __construct(int $number)
    {
        $this->number = $number;
    }

    /**
     * @return int
     */
    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * @return string|null
     */
    public function getFunctionDeclarationFile(): ?string
    {
        return $this->function_declaration_file;
    }

    /**
     * @param string $function_declaration_file
     */
    public function setFunctionDeclarationFile(string $function_declaration_file)
    {
        $this->function_declaration_file = $function_declaration_file;
    }
}
