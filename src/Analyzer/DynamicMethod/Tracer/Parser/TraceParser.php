<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser;

use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\AbstractRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\EntryRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ExitRecord;
use Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Record\ReturnRecord;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Parses trace files to a list of objects representing each record.
 */
class TraceParser
{
    const ENTRY_RECORD_NAME  = 'entries';
    const RETURN_RECORD_NAME = 'returns';

    /**
     * @var string[] [prophecy class => actual class]
     */
    private $prophecy_namespaces = [];

    /**
     * @var string
     */
    private $input_trace_file;

    /**
     * @param string $input_trace_file
     */
    public function __construct(string $input_trace_file)
    {
        $this->input_trace_file = $input_trace_file;
    }

    /**
     * Takes the trace file and outputs it as a list of record
     * objects.
     *
     * @return AbstractRecord[] ['entries' => EntryRecord[], 'returns' => ReturnRecords[]]
     * @throws \InvalidArgumentException
     */
    public function parse(): array
    {
        $file_system = new Filesystem();
        if (!$file_system->exists($this->input_trace_file)) {
            throw new \InvalidArgumentException(sprintf('Trace not found: %s', $this->input_trace_file));
        }

        return $this->generateRecordList();
    }

    /**
     * Takes the list of string records and converts them to
     * a list of record objects.
     *
     * @return AbstractRecord[]
     */
    private function generateRecordList(): array
    {
        $records  = [self::ENTRY_RECORD_NAME => [], self::RETURN_RECORD_NAME => []];
        $line_nr  = 0;
        $handle   = fopen($this->input_trace_file, 'rb');
        $line_max = $this->getTraceSize() - 2;
        while (($buffer = fgets($handle)) !== false) {
            $line_nr++;
            if ($line_nr <= 3 || $line_nr >= $line_max) {
                continue;
            }

            $record = $this->createRecord(trim($buffer));

            if ($record instanceof ExitRecord) {
                continue;
            }
            if ($record instanceof EntryRecord) {
                $records[self::ENTRY_RECORD_NAME][] = $record;
                continue;
            }
            if ($record instanceof ReturnRecord) {
                $records[self::RETURN_RECORD_NAME][] = $record;
            }
        }

        return $records;
    }

    /**
     * Determines whether a record line from a string either is an entry-, exit-, or
     * return record.
     *
     * @see https://xdebug.org/docs/all_settings#trace_format
     * @param string $record_line
     * @return AbstractRecord ReturnRecord|EntryRecord|ExitRecord
     */
    private function createRecord(string $record_line): AbstractRecord
    {
        $record_fields        = explode("\t", $record_line);
        $amount_records_field = count($record_fields);

        if ($amount_records_field === 6) {
            return new ReturnRecord((int) $record_fields[1], $this->extractProphecy($record_fields[5]));
        } elseif ($amount_records_field >= 11) {
            $this->handleProphecyCreation($record_fields);

            $parameters = array_slice($record_fields, 11);
            array_walk($parameters, function (&$param) {
                $param = $this->extractProphecy($param);
            });

            // TODO - Remove magic numbers
            return new EntryRecord(
                (int) $record_fields[1],
                $record_fields[EntryRecord::FUNCTION_NAME_INDEX],
                ((int) $record_fields[6]) === 1,
                $record_fields[8],
                $parameters
            );
        }

        return new ExitRecord((int) $record_fields[1]);
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
}
