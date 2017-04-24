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
     * @var int
     */
    private $function_nr;

    /**
     * @var string
     */
    private $function_declaration_file;

    /**
     * @param int $function_nr
     */
    public function __construct(int $function_nr)
    {
        $this->function_nr = $function_nr;
    }

    /**
     * @return int
     */
    public function getFunctionNr(): int
    {
        return $this->function_nr;
    }

    /**
     * @return string
     */
    public function getFunctionDeclarationFile(): string
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
