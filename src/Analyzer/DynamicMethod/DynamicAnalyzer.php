<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedCall;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedReturn;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Exception\TraceNotFoundException;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Mapper\TracerPhpTypeMapper;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\AbstractRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\TraceParser;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Tracer;
use Hostnet\Component\TypeInference\Analyzer\FunctionAnalyzerInterface;
use Hostnet\Component\TypeInference\CodeEditor\CodeEditorFile;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Uses dynamic analysis to collect argument- and return types from functions calls
 * in a target project.
 */
final class DynamicAnalyzer implements FunctionAnalyzerInterface
{
    /**
     * Prefix used for logs outputted by this class. Name used
     * by stopwatch for this class.
     */
    const TIMER_LOG_NAME = 'DYNAMIC_ANALYSIS';

    /**
     * @var string
     */
    private $target_project;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Collects {@link AnalyzedFunction} by using dynamic analysis.
     *
     * @param string $target_project
     * @return AnalyzedFunction[]
     * @throws TraceNotFoundException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws IOException
     */
    public function collectAnalyzedFunctions(string $target_project): array
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start(self::TIMER_LOG_NAME);

        $this->logger->info(self::TIMER_LOG_NAME . ': Started dynamic analysis');
        $this->target_project = $target_project;

        $tracer = new Tracer($target_project . Tracer::OUTPUT_FOLDER_NAME, $target_project, dirname(__DIR__, 3));
        $tracer->generateTrace();

        $parser  = new TraceParser($tracer->getFullOutputTracePath());
        $records = $parser->parse();

        $entries = $this->filterEntriesToTargetProject($records[TraceParser::ENTRY_RECORD_NAME]);
        $returns = $this->filterReturnsToTargetProject($records[TraceParser::RETURN_RECORD_NAME], $entries);

        $analyzed_functions = $this->mapRecordsToAnalysedFunctions(array_merge($entries, $returns));
        $this->logger->info(self::TIMER_LOG_NAME . ': Finished dynamic analysis ({time}s)', [
            'time' => round($stopwatch->stop(self::TIMER_LOG_NAME)->getDuration() / 1000, 2)
        ]);
        return $analyzed_functions;
    }

    /**
     * Takes an array of AbstractRecords and creates AnalyzedFunctions.
     * In case a record is an EntryRecord, a new AnalyzedCall is added
     * to the created AnalyzedFunction. In case the record is a ReturnRecord,
     * an AnalyzedReturn is added to the AnalyzedFunction. This results in a
     * collection containing all analyzed functions with all calls that have
     * been made to that function and types that have been returned.
     *
     * @param AbstractRecord[] $records
     * @return AnalyzedFunction[]
     * @throws \InvalidArgumentException
     */
    private function mapRecordsToAnalysedFunctions(array $records): array
    {
        $collection          = new AnalyzedFunctionCollection();
        $function_number_map = [];
        foreach ($records as $record) {
            if ($record instanceof EntryRecord) {
                $function_number_map[$record->getNumber()] = $record->getFunctionName();
            }
        }

        foreach ($records as $record) {
            $function_name      = $function_number_map[$record->getNumber()];
            list($namespace,
                $class_name,
                $function_name) = TracerPhpTypeMapper::extractTraceFunctionName($function_name);

            $file     = $record->getFunctionDeclarationFile();
            $class    = new AnalyzedClass($namespace, $class_name, $file, null, [], [$function_name]);
            $function = new AnalyzedFunction($class, $function_name);

            if ($record instanceof ReturnRecord) {
                $function->addCollectedReturn(
                    new AnalyzedReturn(TracerPhpTypeMapper::toPhpType($record->getReturnValue()))
                );
            }
            if ($record instanceof EntryRecord) {
                $function->addCollectedArguments(new AnalyzedCall(array_map(function ($param) {
                    return TracerPhpTypeMapper::toPhpType($param);
                }, $record->getParameters())));
            }

            $collection->add($function);
        }

        return $collection->getAll();
    }

    /**
     * Returns a filtered set of entries from which the functions are defined in the
     * target project (vendor folder excluded).
     *
     * @param EntryRecord[] $entries
     * @return EntryRecord[]
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function filterEntriesToTargetProject(array $entries): array
    {
        $entries_in_project = [];

        foreach ($entries as $entry) {
            if (!$entry->isUserDefined()
                || strpos($entry->getFileName(), $this->target_project) === false
                || strpos($entry->getFileName(), '/vendor/') !== false
            ) {
                continue;
            }
            list($namespace,
                $class_name,
                $function_name) = TracerPhpTypeMapper::extractTraceFunctionName($entry->getFunctionName());

            if ($namespace === TracerPhpTypeMapper::NAMESPACE_GLOBAL
                || $function_name === TracerPhpTypeMapper::FUNCTION_CLOSURE) {
                continue;
            }

            try {
                $file = $this->getDefinitionFile($namespace, $class_name, $function_name);
            } catch (FileNotFoundException $e) {
                continue;
            }

            $entry->setFunctionDeclarationFile($file->getPath());
            $entries_in_project[] = $entry;
        }

        return $entries_in_project;
    }

    /**
     * Returns a set of return records that contain the same function number as
     * the functions in the list of EntryRecords.
     *
     * @param ReturnRecord[] $returns
     * @param EntryRecord[] $functions_to_match
     * @return ReturnRecord[]
     */
    private function filterReturnsToTargetProject(array $returns, array $functions_to_match): array
    {
        $matching_returns = [];
        $entries          = [];

        foreach ($functions_to_match as $entry) {
            $entries[$entry->getNumber()] = $entry->getFunctionDeclarationFile();
        }

        foreach ($returns as $return) {
            if (isset($entries[$return->getNumber()])) {
                $return->setFunctionDeclarationFile($entries[$return->getNumber()]);
                $matching_returns[] = $return;
            }
        }

        return $matching_returns;
    }

    /**
     * Returns the file that declares a function within the target project, if present.
     *
     * @param string $namespace
     * @param string $class_name
     * @param string $function_name
     * @return CodeEditorFile
     * @throws FileNotFoundException When function not defined in target project
     * @throws \RuntimeException When failing to retrieve the contents of a file
     * @throws \InvalidArgumentException When searching in invalid directory
     */
    private function getDefinitionFile(string $namespace, string $class_name, string $function_name): CodeEditorFile
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in($this->target_project)
            ->exclude('vendor')
            ->name('*.php');

        foreach ($finder as $file) {
            $file_contents = $file->getContents();
            if (preg_match(sprintf('/class\s%s(\s|$)/', $class_name), $file_contents) === 1 &&
                strpos($file_contents, sprintf('namespace %s;', $namespace)) !== false &&
                strpos($file_contents, sprintf('function %s', $function_name)) !== false
            ) {
                return new CodeEditorFile($file->getRealPath(), $file_contents);
            }
        }

        throw new FileNotFoundException(
            sprintf("File containing '%s\\%s::%s' not found.", $namespace, $class_name, $function_name)
        );
    }
}
