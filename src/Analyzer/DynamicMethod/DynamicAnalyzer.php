<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\FunctionAnalyzerInterface;

/**
 * Uses dynamic analysis to collect argument- and return types from functions calls
 * in a target project.
 */
final class DynamicAnalyzer implements FunctionAnalyzerInterface
{
    /**
     * Collects {@link AnalyzedFunction} by using dynamic analysis.
     *
     * @param string $target_project
     * @return AnalyzedFunction[]
     */
    public function collectAnalyzedFunctions(string $target_project): array
    {
        // TODO - Do some actual dynamic analysis on the target project (like Prototype 2)
        return [];
    }
}
