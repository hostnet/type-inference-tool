<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\CodeEditor\Instruction;

use Hostnet\Component\TypeInference\Analyzer\Data\PhpType;
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
        $namespace   = 'ExampleProject\\Component';
        $class_name  = 'ExampleClass';
        $return_type = new PhpType('string');

        $instruction_single_lined = new ReturnTypeInstruction($namespace, $class_name, 'singleLineFunc', $return_type);
        $instruction_single_lined->apply($this->fixtures_dir . $this->target_project);

        $instruction_multi_lined = new ReturnTypeInstruction($namespace, $class_name, 'multiLineFunc', $return_type);
        $instruction_multi_lined->apply($this->fixtures_dir . $this->target_project);

        self::assertFileEquals(
            $this->fixtures_dir . $this->project_expected . $this->example_class,
            $this->fixtures_dir . $this->target_project . $this->example_class
        );
    }

    public function testInstructionShouldNotBeAppliedWhenTargetNotFound()
    {
        $instruction = new ReturnTypeInstruction('Does\\Not\\Exists', 'Invalid', 'NonExistent', new PhpType('float'));
        $instruction->apply($this->fixtures_dir . $this->target_project);

        self::assertFileEquals(
            $this->fixtures_dir . $this->project_before . $this->example_class,
            $this->fixtures_dir . $this->target_project . $this->example_class
        );
    }
}
