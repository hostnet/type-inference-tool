<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

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
