<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;

/**
 * Classes implementing this interface return a list containing
 * AnalyzedFunctions. These should be retrieved by analyzing the target
 * project.
 */
interface FunctionAnalyzerInterface
{
    /**
     * @param string $target_project
     * @return AnalyzedFunction[]
     */
    public function collectAnalyzedFunctions(string $target_project): array;
}
