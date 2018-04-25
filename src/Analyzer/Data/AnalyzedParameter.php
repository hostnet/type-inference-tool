<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Mapper\TracerPhpTypeMapper;

/**
 * Holds parameters data used with AnalyzedFunctions.
 */
final class AnalyzedParameter
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $type;

    /**
     * @var bool
     */
    private $has_default_value;

    /**
     * @var bool
     */
    private $has_type_hint;

    /**
     * @param string $name
     * @param string $type
     * @param bool $has_default_value
     * @param bool $has_type_hint
     */
    public function __construct(
        string $name = '',
        string $type = TracerPhpTypeMapper::TYPE_UNKNOWN,
        bool $has_default_value = false,
        bool $has_type_hint = false
    ) {
        $this->name              = $name;
        $this->type              = $type;
        $this->has_default_value = $has_default_value;
        $this->has_type_hint     = $has_type_hint;
    }
    /**
     * @param string $type
     */
    public function setType(string $type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param bool $has_default_value
     */
    public function setHasDefaultValue(bool $has_default_value)
    {
        $this->has_default_value = $has_default_value;
    }

    /**
     * @return bool
     */
    public function hasDefaultValue(): bool
    {
        return $this->has_default_value;
    }

    /**
     * @return bool
     */
    public function hasTypeHint(): bool
    {
        return $this->has_type_hint;
    }

    /**
     * @param bool $has_type_hint
     */
    public function setHasTypeHint(bool $has_type_hint)
    {
        $this->has_type_hint = $has_type_hint;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param $name string
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }
}
