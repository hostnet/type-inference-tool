<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record;

/**
 * Represents an entry record in a function trace. An entry records is a function-call
 * that has been executed.
 */
final class EntryRecord extends AbstractRecord
{
    const FUNCTION_NAME_INDEX = 5;

    /**
     * @var string
     */
    private $function_name;

    /**
     * @var bool
     */
    private $user_defined;

    /**
     * @var string
     */
    private $file_name;

    /**
     * @var string[]
     */
    private $parameters;

    /**
     * @param int $function_nr
     * @param string $function_name
     * @param bool $user_defined
     * @param string $file_name
     * @param string[] $parameters
     */
    public function __construct(
        int $function_nr,
        string $function_name,
        bool $user_defined,
        string $file_name,
        array $parameters
    ) {
        parent::__construct($function_nr);
        $this->function_name = $function_name;
        $this->user_defined  = $user_defined;
        $this->file_name     = $file_name;
        $this->parameters    = $parameters;
    }

    /**
     * @return string
     */
    public function getFunctionName(): string
    {
        return $this->function_name;
    }

    /**
     * @return bool
     */
    public function isUserDefined(): bool
    {
        return $this->user_defined;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->file_name;
    }

    /**
     * @return string[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
