<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Exception\TraceNotFoundException;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Mapper\TracerPhpTypeMapper;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\AbstractRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Storage\RecordStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Parses trace files to a list of objects representing each record.
 */
class TraceParser
{
    const LOG_PREFIX         = 'TRACE_PARSER: ';
    const ENTRY_RECORD_NAME  = 'entries';
    const RETURN_RECORD_NAME = 'returns';

    /**
     * @var string
     */
    private $target_project;

    /**
     * @var string[] [prophecy class => actual class]
     */
    private $prophecy_namespaces = [];

    /**
     * @var string
     */
    private $input_trace_file;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string[]
     */
    private $function_location_cache = [];

    /**
     * @var string[]
     */
    private $target_project_functions = [];

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var RecordStorageInterface
     */
    private $storage;

    /**
     * @param string $target_project
     * @param string $input_trace_file
     * @param RecordStorageInterface $storage
     * @param string[] $ignored_folders
     * @param LoggerInterface $logger
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $target_project,
        string $input_trace_file,
        RecordStorageInterface $storage,
        array $ignored_folders,
        LoggerInterface $logger
    ) {
        $this->logger           = $logger;
        $this->target_project   = $target_project;
        $this->input_trace_file = $input_trace_file;
        $this->storage          = $storage;
        $this->finder           = new Finder();
        $this->finder
            ->files()
            ->in($this->target_project)
            ->exclude($ignored_folders)
            ->name('*.php');
    }

    /**
     * Takes the trace file and outputs it as a list of record
     * objects.
     *
     * @throws TraceNotFoundException
     */
    public function parse()
    {
        $this->logger->debug(self::LOG_PREFIX . 'Started parsing generated trace...');

        $file_system = new Filesystem();
        if (!$file_system->exists($this->input_trace_file)) {
            throw new TraceNotFoundException(sprintf('Trace not found: %s', $this->input_trace_file));
        }

        $this->generateRecordList();
        $this->logger->debug(self::LOG_PREFIX . 'Finished parsing generated trace');
    }

    /**
     * Takes the list of string records and converts them to
     * a list of record objects.
     */
    private function generateRecordList()
    {
        $this->generateFunctionLocationCache();

        $line_nr  = 0;
        $handle   = fopen($this->input_trace_file, 'rb');
        $line_max = $this->getTraceSize() - 2;

        while (($buffer = fgets($handle)) !== false) {
            $line_nr++;

            if ($line_nr <= 3 || $line_nr >= $line_max) {
                continue;
            }

            $record = $this->createRecord(trim($buffer));

            if ($record === null) {
                continue;
            }

            if ($record instanceof EntryRecord
                && strpos($record->getFileName(), $this->target_project . 'vendor/') === false
            ) {
                $this->target_project_functions[] = $record->getNumber();
                $this->storage->appendEntryRecord($record);
                continue;
            }
            if ($record instanceof ReturnRecord) {
                $this->storage->appendReturnRecord($record);
            }
        }
        fclose($handle);
        $this->storage->finishInsertion();
    }

    /**
     * Determines whether a record line from a string either is an entry-, exit-, or
     * return record.
     *
     * @see https://xdebug.org/docs/all_settings#trace_format
     * @param string $record_line
     * @return AbstractRecord ReturnRecord|EntryRecord|ExitRecord
     */
    private function createRecord(string $record_line)
    {
        $record_fields        = explode("\t", $record_line);
        $amount_records_field = count($record_fields);

        if ($amount_records_field === 6) {
            $function_number = (int) $record_fields[1];

            if (!in_array($function_number, $this->target_project_functions, true)) {
                return null;
            }

            return new ReturnRecord($function_number, $this->extractProphecy($record_fields[5]));
        }

        if ($amount_records_field >= 11) {
            $this->handleProphecyCreation($record_fields);

            $file_name = $record_fields[8];

            if (strpos($file_name, $this->target_project . '/vendor/') !== false) {
                return null;
            }

            [$namespace,
                $class_name,
                $function_name] = TracerPhpTypeMapper::extractTraceFunctionName(
                    $record_fields[EntryRecord::FUNCTION_NAME_INDEX]
                );

            if ($namespace === TracerPhpTypeMapper::NAMESPACE_GLOBAL
                || $function_name === TracerPhpTypeMapper::FUNCTION_CLOSURE
                || $function_name === null
            ) {
                return null;
            }

            $index_key = $namespace . '\\'. $class_name . '::'. $function_name;

            if (!array_key_exists($index_key, $this->function_location_cache)) {
                return null;
            }

            if (null === ($file = $this->function_location_cache[$index_key])) {
                return null;
            }

            $parameters = array_slice($record_fields, 11);
            array_walk($parameters, function (&$param) {
                $param = $this->extractProphecy($param);
            });

            $entry_record =  new EntryRecord(
                (int) $record_fields[1],
                $record_fields[EntryRecord::FUNCTION_NAME_INDEX],
                ((int) $record_fields[6]) === 1,
                $file_name,
                $parameters
            );

            $entry_record->setFunctionDeclarationFile($file);

            return $entry_record;
        }

        return null;
    }

    /**
     * Checks whether the given type is of a prophecy object, is so returns the
     * corresponding type of the object that is being mocked.
     *
     * @param string $type
     * @return string
     */
    private function extractProphecy(string $type): string
    {
        if (strpos($type, 'Double\\') === false) {
            return $type;
        }

        foreach ($this->prophecy_namespaces as $prophecy_namespace => $php_type) {
            if (strpos($type, $prophecy_namespace) !== false) {
                return (strpos($type, 'class ') !== false ? 'class ' : '') . $php_type;
            }
        }

        return $type;
    }

    /**
     * Checks whether a prophecy object gets created. If so, there will be
     * determined whether the prophecy object implements an interface or extends
     * a class. The prophecy-used namespace and the original namespace of the interface
     * or class that is being mocked will be mapped. That map is used to prevent that
     * a prophecy will be type hinted, but instead the class/interface that is being
     * mocked should be type hinted.
     *
     * @param string[] $record_fields Row from a trace file
     */
    private function handleProphecyCreation(array $record_fields)
    {
        if ($record_fields[EntryRecord::FUNCTION_NAME_INDEX] !== 'eval' ||
            strpos($record_fields[8], 'Prophecy/Doubler/Generator/ClassCreator.php') === false
        ) {
            return;
        }

        $prophecy_object_statement = stripslashes(str_replace('\n', PHP_EOL, $record_fields[7]));
        preg_match('/Double\\\(\w+\\\?)+/', $prophecy_object_statement, $matching_prophecy_namespaces);
        $prophecy_namespace = $matching_prophecy_namespaces[0];

        preg_match('/implements (.+) {/', $prophecy_object_statement, $found_implements);
        $prophecy_implementations = explode(', ', $found_implements[1]);

        if (count($prophecy_implementations) > 2) {
            $php_mapped_type                                =  array_pop($prophecy_implementations);
            $this->prophecy_namespaces[$prophecy_namespace] = ltrim($php_mapped_type, '\\');
            return;
        }

        preg_match('/extends \\\(.+) implements/', $prophecy_object_statement, $found_extends);
        $this->prophecy_namespaces[$prophecy_namespace] = ltrim($found_extends[1], '\\');
    }

    /**
     * Returns the amount of lines the trace file has.
     *
     * @return int
     */
    private function getTraceSize(): int
    {
        $total_lines = 0;
        $handle      = fopen($this->input_trace_file, 'rb');

        while (($buffer = fgets($handle)) !== false) {
            $total_lines++;
        }

        return $total_lines;
    }

    /**
     * Creates an index of all classes (fully qualified namespaces) and their
     * file location. Used to quickly retrieve the file location of a specific
     * class.
     */
    private function generateFunctionLocationCache()
    {
        $this->logger->debug(self::LOG_PREFIX . 'Indexing target project files...');

        $this->finder->getIterator()->rewind();

        foreach ($this->finder as $file) {
            $file_contents = $file->getContents();

            preg_match('/namespace\s+([\w_|\\\\]+);/', $file_contents, $namespace);
            $namespace = $namespace[1] ?? TracerPhpTypeMapper::NAMESPACE_GLOBAL;

            preg_match('/(class|trait|interface)\s+([\w_]+).*\s*\n*(.*\n)*{/', $file_contents, $class_name);
            $class_name = $class_name[2] ?? TracerPhpTypeMapper::NAMESPACE_GLOBAL;

            preg_match_all('/function\s+([\w_]+)\(/', $file_contents, $functions);
            $functions = $functions[1];

            foreach ($functions as $function) {
                $key                                 = $namespace . '\\'. $class_name . '::'. $function;
                $this->function_location_cache[$key] = $file->getRealPath();
            }
        }

        $this->logger->debug(self::LOG_PREFIX . 'Finished indexing target project files');
    }
}
