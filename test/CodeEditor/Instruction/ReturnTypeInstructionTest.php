<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\CodeEditor\Instruction;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedClass;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Hostnet\Component\TypeInference\CodeEditor\Instruction\ReturnTypeInstruction
 * @covers \Hostnet\Component\TypeInference\CodeEditor\Instruction\AbstractInstruction
 */
class ReturnTypeInstructionTest extends TestCase
{
    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var string
     */
    private $fixtures_dir;

    private $target_project   = '/ExampleReturnTypes/ExampleProject-target';
    private $project_before   = '/ExampleReturnTypes/ExampleProject-before';
    private $project_expected = '/ExampleReturnTypes/ExampleProject-expected';
    private $example_class    = '/src/ExampleClass.php';

    protected function setUp()
    {
        $this->fixtures_dir = dirname(__DIR__, 2) . '/Fixtures';

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
        $class       = new AnalyzedClass('ExampleProject\\Component', 'ExampleClass', '', null, [], []);
        $type_string = new ScalarPhpType(ScalarPhpType::TYPE_STRING);
        $type_bool   = new ScalarPhpType(ScalarPhpType::TYPE_BOOL);

        $instruction_single_lined = new ReturnTypeInstruction($class, 'singleLineFunc', $type_string);
        $result_1                 = $instruction_single_lined->apply($this->fixtures_dir . $this->target_project);

        $instruction_multi_lined = new ReturnTypeInstruction($class, 'multiLineFunc', $type_string);
        $result_2                = $instruction_multi_lined->apply($this->fixtures_dir . $this->target_project);

        $instruction_abstract = new ReturnTypeInstruction($class, 'abstractFunction', $type_bool);
        $result_3             = $instruction_abstract->apply($this->fixtures_dir . $this->target_project);

        self::assertTrue($result_1);
        self::assertTrue($result_2);
        self::assertTrue($result_3);
        self::assertFileEquals(
            $this->fixtures_dir . $this->project_expected . $this->example_class,
            $this->fixtures_dir . $this->target_project . $this->example_class
        );
    }

    public function testInstructionShouldNotBeAppliedWhenTargetNotFound()
    {
        $type_float = new ScalarPhpType(ScalarPhpType::TYPE_FLOAT);

        $non_existent_class = new AnalyzedClass('Does\\Not\\Exists', 'Invalid', '', null, [], []);
        $instruction        = new ReturnTypeInstruction($non_existent_class, 'NonExistent', $type_float);
        $result             = $instruction->apply($this->fixtures_dir . $this->target_project);

        self::assertSame($type_float, $instruction->getTargetReturnType());
        self::assertFalse($result);
        self::assertFileEquals(
            $this->fixtures_dir . $this->project_before . $this->example_class,
            $this->fixtures_dir . $this->target_project . $this->example_class
        );
    }

    public function testInstructionShouldFailWhenFileNotInTargetProject()
    {
        $class       = new AnalyzedClass('ExampleProject\\Component', 'ExampleClass', '', null, [], []);
        $return_type = new ScalarPhpType(ScalarPhpType::TYPE_STRING);
        $instruction = new ReturnTypeInstruction($class, 'singleLineFunc', $return_type);

        self::assertFalse($instruction->apply($this->fixtures_dir . $this->project_expected));
    }

    public function testApplyWithDiffHandlerShouldInvokeCallback()
    {
        $file        = $this->fixtures_dir . $this->target_project . $this->example_class;
        $class       = new AnalyzedClass('ExampleProject\\Component', 'ExampleClass', $file, null, [], []);
        $return_type = new ScalarPhpType(ScalarPhpType::TYPE_STRING);
        $instruction = new ReturnTypeInstruction($class, 'singleLineFunc', $return_type);

        $before      = '';
        $after       = '';
        $edited_path = '';
        $handler     = function (string $original, string $new, string $path) use (&$before, &$after, &$edited_path) {
            $before      = $original;
            $after       = $new;
            $edited_path = $path;
        };

        self::assertTrue($instruction->apply($this->fixtures_dir . $this->target_project, $handler));
        self::assertStringEqualsFile($this->fixtures_dir . $this->project_before . $this->example_class, $before);
        self::assertStringEqualsFile($this->fixtures_dir . $this->target_project . $this->example_class, $after);
        self::assertSame($file, $edited_path);
    }

    public function testApplyWithoutOverwritingShouldNotUpdateActualFiles()
    {
        $file        = $this->fixtures_dir . $this->target_project . $this->example_class;
        $class       = new AnalyzedClass('ExampleProject\\Component', 'ExampleClass', $file, null, [], []);
        $return_type = new ScalarPhpType(ScalarPhpType::TYPE_STRING);
        $instruction = new ReturnTypeInstruction($class, 'singleLineFunc', $return_type);

        self::assertTrue($instruction->apply($this->fixtures_dir . $this->target_project, null, false));
        self::assertFileEquals(
            $this->fixtures_dir . $this->project_before . $this->example_class,
            $this->fixtures_dir . $this->target_project . $this->example_class
        );
    }
}
