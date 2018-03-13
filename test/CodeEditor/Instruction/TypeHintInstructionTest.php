<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\CodeEditor\Instruction;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Hostnet\Component\TypeInference\CodeEditor\Instruction\TypeHintInstruction
 */
class TypeHintInstructionTest extends TestCase
{
    private $target_project   = '/ExampleTypeHints/ExampleProject-target';
    private $project_before   = '/ExampleTypeHints/ExampleProject-before';
    private $project_expected = '/ExampleTypeHints/ExampleProject-expected';
    private $example_class    = '/src/ExampleClass.php';

    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var AnalyzedClass
     */
    private $class;

    /**
     * @var string
     */
    private $fixtures_dir;

    protected function setUp()
    {
        $this->fixtures_dir = dirname(__DIR__, 2) . '/Fixtures';

        $namespace   = 'ExampleProject\\Component';
        $class_name  = 'ExampleClass';
        $this->class = new AnalyzedClass($namespace, $class_name, '', null, [], []);

        $this->fs = new Filesystem();
        $this->fs->copy(
            $this->fixtures_dir . $this->project_before . $this->example_class,
            $this->fixtures_dir . $this->target_project . $this->example_class
        );
    }

    protected function tearDown()
    {
        $this->fs->remove([$this->fixtures_dir . $this->target_project . $this->example_class]);
    }

    public function testInstructionShouldAddAReturnTypeToFunctionDeclaration()
    {
        $arg_number = 0;
        $type_hint  = new ScalarPhpType(ScalarPhpType::TYPE_INT);

        $instruction_single_lined = new TypeHintInstruction($this->class, 'singleLineFunc', $arg_number, $type_hint);
        $instruction_single_lined->apply($this->fixtures_dir . $this->target_project);

        $instruction_multi_lines = new TypeHintInstruction($this->class, 'multiLineFunc', $arg_number, $type_hint);
        $instruction_multi_lines->apply($this->fixtures_dir . $this->target_project);

        self::assertFileEquals(
            $this->fixtures_dir . $this->project_expected . $this->example_class,
            $this->fixtures_dir . $this->target_project . $this->example_class
        );
        self::assertSame($arg_number, $instruction_single_lined->getTargetArgNumber());
        self::assertSame($type_hint, $instruction_single_lined->getTargetTypeHint());
    }

    public function testWhenFunctionNotDefinedInProjectThenDoNotUpdateFile()
    {
        $invalid_instruction = new TypeHintInstruction(
            $this->class,
            'nonExistentFunction',
            0,
            new ScalarPhpType(ScalarPhpType::TYPE_INT)
        );

        self::assertFalse($invalid_instruction->apply($this->fixtures_dir . $this->project_before));
    }
}
