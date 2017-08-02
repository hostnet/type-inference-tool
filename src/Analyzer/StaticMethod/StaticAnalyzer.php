<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Mapper\TracerPhpTypeMapper;
use Hostnet\Component\TypeInference\Analyzer\FunctionAnalyzerInterface;
use Hostnet\Component\TypeInference\Analyzer\StaticMethod\AstConverter\PhpAstConverter;
use Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor\DocblockNodeVisitor;
use Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor\FunctionNodeVisitor;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Uses static analysis to collect argument- and return types from functions calls
 * in a target project.
 */
final class StaticAnalyzer implements FunctionAnalyzerInterface
{
    /**
     * Prefix used for logs outputted by this class. Name used
     * by stopwatch for this class.
     */
    const TIMER_LOG_NAME = 'STATIC_ANALYSIS';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string[]
     */
    private $ignored_folders;

    /**
     * @param string[] $ignored_folders
     * @param LoggerInterface $logger
     */
    public function __construct(array $ignored_folders, LoggerInterface $logger = null)
    {
        $this->logger          = $logger ?? new NullLogger();
        $this->ignored_folders = $ignored_folders;
    }

    /**
     * Collects {@link AnalyzedFunction} by using static analysis.
     *
     * @param string $target_project
     * @return AnalyzedFunction[]
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function collectAnalyzedFunctions(string $target_project): array
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start(self::TIMER_LOG_NAME);
        $this->logger->info(self::TIMER_LOG_NAME . ': Started static analysis');

        $analyzed_functions = new AnalyzedFunctionCollection();

        $this->analyseMethods($analyzed_functions, $target_project);
        $this->analyseDocblocks($analyzed_functions, $target_project);

        $this->logger->info(self::TIMER_LOG_NAME . ': Finished static analysis ({time}s)', [
            'time' => round($stopwatch->stop(self::TIMER_LOG_NAME)->getDuration() / 1000, 2)
        ]);

        return $analyzed_functions->getAll();
    }

    /**
     * Collects defined type hints from source files. These type hints are used during
     * type inference analysis. This means that inferred type hints are also based on
     * docblocks.
     *
     * @param AnalyzedFunctionCollection $functions
     * @param string $target_project
     */
    private function analyseDocblocks(AnalyzedFunctionCollection $functions, string $target_project)
    {
        $this->analyseSourceAsts(
            $target_project,
            function (array $ast, SplFileInfo $file, int $current, int $total) use ($functions, $target_project) {
                $this->logger->debug(self::TIMER_LOG_NAME . ' - ANALYSING_DOCBLOCKS: ' . $file->getFilename() . ' ('
                    . $current . '/' . $total . ')');

                $docblock_visitor = new DocblockNodeVisitor(
                    $functions,
                    $this->retrieveSourceFiles($target_project)
                );
                $this->travelTree($ast, $docblock_visitor);
            }
        );
    }

    /**
     * Collects function data by traversing AST's of all source files. These
     * AnalyzedFunctions are used to infer parameter- and return types.
     *
     * @param AnalyzedFunctionCollection $analyzed_function_collection
     * @param string $target_project
     */
    private function analyseMethods(AnalyzedFunctionCollection $analyzed_function_collection, string $target_project)
    {
        $function_index = $this->createFunctionIndex($target_project);

        $this->analyseSourceAsts(
            $target_project,
            function (
                array $ast,
                SplFileInfo $file,
                int $current,
                int $total
            ) use (
                $analyzed_function_collection,
                &$function_index
            ) {
                $this->logger->debug(self::TIMER_LOG_NAME . ' - ANALYSING_METHODS: ' . $file->getFilename() . ' ('
                    . $current . '/' . $total . ')');

                $abstract_syntax_tree = $this->travelTree($ast, new NameResolver());
                $function_visitor     = new FunctionNodeVisitor(
                    $analyzed_function_collection,
                    $file->getRealPath(),
                    $function_index
                );
                $this->travelTree($abstract_syntax_tree, $function_visitor);
            }
        );
    }

    /**
     * Used to apply callbacks to source files and their AST's. Using the arguments of
     * the callback, files and their AST's could be analyzed using node visitors.
     *
     * @param string $target_project
     * @param callable $execute Callback with arguments Node[] (AST) and SplFileInfo
     */
    private function analyseSourceAsts(string $target_project, callable $execute)
    {
        $project_files = $this->retrieveProjectFiles($target_project);
        $ast_converter = new PhpAstConverter();

        $total_files  = count($project_files);
        $current_file = 0;

        foreach ($project_files as $file) {
            $current_file++;
            $abstract_syntax_tree = $ast_converter->convert($file->getContents());
            $execute($abstract_syntax_tree, $file, $current_file, $total_files);
        }
    }

    /**
     * Uses a node visitor to travel through an abstract syntax tree.
     *
     * @param Node[] $abstract_syntax_tree
     * @param NodeVisitorAbstract $node_visitor
     * @return Node[] AST after traversal
     */
    private function travelTree(array $abstract_syntax_tree, NodeVisitorAbstract $node_visitor): array
    {
        $ast_node_traveller = new NodeTraverser();
        $ast_node_traveller->addVisitor($node_visitor);
        return $ast_node_traveller->traverse($abstract_syntax_tree);
    }

    /**
     * Returns a Finder with the files matches to the target project its
     * source-files.
     *
     * @param string $target_project
     * @return Finder
     * @throws \InvalidArgumentException
     */
    private function retrieveProjectFiles(string $target_project): Finder
    {
        return (new Finder())
            ->files()
            ->in($target_project)
            ->exclude($this->ignored_folders)
            ->name('*.php');
    }

    /**
     * Returns a Finder with the files matches to all the php files in the
     * directory. This is used during traversal to retrieve the docblocks
     * from other classes (including vendor classes).
     *
     * @param string $target_project
     * @return Finder
     * @throws \InvalidArgumentException
     */
    private function retrieveSourceFiles(string $target_project): Finder
    {
        return (new Finder())
            ->files()
            ->in($target_project)
            ->name('*.php');
    }

    /**
     * Traverses all files in the target project and collects all functions per
     * class. This index is used to retrieve the file location and functions
     * during node traversal.
     *
     * @param string $target_project
     * @return string[] [fqcn => [file path, [functions]]]
     */
    private function createFunctionIndex(string $target_project): array
    {
        $function_index = [];
        $all_files      = $this->retrieveSourceFiles($target_project);

        $this->logger->debug(self::TIMER_LOG_NAME . ': Indexing functions in target project...');

        foreach ($all_files as $file) {
            $file_contents = $file->getContents();

            preg_match('/namespace\s+([\w_|\\\\]+);/', $file_contents, $namespace);
            $namespace = $namespace[1] ?? TracerPhpTypeMapper::NAMESPACE_GLOBAL;

            preg_match('/(class|trait|interface)\s+([\w_]+).*\s*\n*{/', $file_contents, $class_name);
            $class_name = $class_name[2] ?? TracerPhpTypeMapper::NAMESPACE_GLOBAL;

            preg_match_all('/function\s+([\w_]+)\(/', $file_contents, $functions);
            $functions = $functions[1];

            preg_match_all('/use ([\w\\\\]*);/', $file_contents, $use_stmts_matches);
            $use_stmts         = [];
            $use_stmts_matches = array_walk($use_stmts_matches[1], function ($stmt) use (&$use_stmts) {
                $parts                  = explode('\\', $stmt);
                $class_name             = $parts[count($parts) - 1];
                $use_stmts[$class_name] = $stmt;
            });

            $extends_pattern = sprintf('/class %s extends ([\w\\\\]+)/', preg_quote($class_name, '/'));
            preg_match($extends_pattern, $file_contents, $extended_class);
            $extended_class = $extended_class[1] ?? null;

            if ($extended_class !== null) {
                if (array_key_exists($extended_class, $use_stmts)) {
                    $extended_class = $use_stmts[$extended_class];
                } else {
                    $extended_class = $namespace . '\\' . $extended_class;
                }
            }

            $impl_pattern = sprintf('/class %s (extends [\w\\\\]+)?\s*implements\s(([\w\\\\]+(,\s)?)*)/', $class_name);
            preg_match($impl_pattern, $file_contents, $implements);
            $implements = array_key_exists(2, $implements) ? explode(', ', $implements[2]) : [];
            foreach ($implements as $i => $implement) {
                if (array_key_exists($implement, $use_stmts)) {
                    $implements[$i] = $use_stmts[$implement];
                    continue;
                }
                $implements[$i] = $namespace . '\\' . $implement;
            }

            $function_index[$namespace . '\\' . $class_name] = [
                'path' => $file->getRealPath(),
                'methods' => $functions,
                'parents' => array_merge($extended_class !== null ? [$extended_class] : [], $implements)
            ];
        }

        return $function_index;
    }

    /**
     * Lists all methods of the given class and its parents.
     *
     * @param string[] $function_index
     * @param string $current_fqcn
     * @param string[] $methods
     * @return string[]
     */
    public static function listAllMethods(array &$function_index, string $current_fqcn, array $methods = []): array
    {
        if (!array_key_exists($current_fqcn, $function_index)) {
            return $methods;
        }

        foreach ($function_index[$current_fqcn]['methods'] as $current_method) {
            if (!in_array($current_method, $methods, true)) {
                $methods[] = $current_method;
            }
        }

        $parents = $function_index[$current_fqcn]['parents'];
        foreach ($parents as $parent) {
            $methods = self::listAllMethods($function_index, $parent, $methods);
        }

        return $methods;
    }
}
