<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Mapper;

use Hostnet\Component\TypeInference\Analyzer\Data\Type\NonScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\PhpTypeInterface;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\ScalarPhpType;
use Hostnet\Component\TypeInference\Analyzer\Data\Type\UnresolvablePhpType;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\TypeInference\Analyzer\DynamicMethod\Tracer\Parser\Mapper\TracerPhpTypeMapper
 */
class TracerPhpTypeMapperTest extends TestCase
{
    /**
     * @dataProvider tracerPhpTypeProvider
     */
    public function testMapTraceTypeToPhpType(string $trace_type, PhpTypeInterface $php_type)
    {
        self::assertEquals($php_type, TracerPhpTypeMapper::toPhpType($trace_type));
    }

    /**
     * @dataProvider tracerFunctionNameProvider
     */
    public function testExtractTraceFunctionName(string $trace_function_name, array $result)
    {
        self::assertSame($result, TracerPhpTypeMapper::extractTraceFunctionName($trace_function_name));
    }

    public function tracerFunctionNameProvider()
    {
        return [
            ['Hostnet\\Example\\SomeClass->SomeFunction', ['Hostnet\\Example', 'SomeClass', 'SomeFunction']],
            ['Hostnet\\Example\\SomeClass::SomeFunction', ['Hostnet\\Example', 'SomeClass', 'SomeFunction']],
            ['Hostnet\\Example\\SomeClass->ExampleProject\\{closure}', ['Hostnet\\Example', 'SomeClass', '{closure}']],
            ['Hostnet\\Example\\SomeClass', ['Hostnet\\Example', 'SomeClass', null]],
            ['SomeClass->__construct', [TracerPhpTypeMapper::NAMESPACE_GLOBAL, '\\SomeClass', '__construct']],
            ['class ArrayObject', [TracerPhpTypeMapper::NAMESPACE_GLOBAL, '\\ArrayObject', null]],
            ['class Namespace\\Classname', ['Namespace', 'Classname', null]],
            ['class ExampleProject\\SomeObject->SomeFunction', ['ExampleProject', 'SomeObject', 'SomeFunction']],
            [
                'Twig_Loader_Filesystem->__construct',
                [TracerPhpTypeMapper::NAMESPACE_GLOBAL, '\Twig_Loader_Filesystem', '__construct']
            ],
            ['???', [null, TracerPhpTypeMapper::TYPE_UNKNOWN, null]]
        ];
    }

    public function tracerPhpTypeProvider()
    {
        return [
            ['long', new ScalarPhpType(ScalarPhpType::TYPE_INT)],
            ['double', new ScalarPhpType(ScalarPhpType::TYPE_FLOAT)],
            ['true', new ScalarPhpType(ScalarPhpType::TYPE_BOOL)],
            ['false', new ScalarPhpType(ScalarPhpType::TYPE_BOOL)],
            ['string(123)', new ScalarPhpType(ScalarPhpType::TYPE_STRING)],
            ['array(56)', new NonScalarPhpType(TracerPhpTypeMapper::NAMESPACE_GLOBAL, 'array', '', null, [])],
            ['class ExampleProject\\SomeObject', new NonScalarPhpType('ExampleProject', 'SomeObject', '', null, [])],
            [
                'class ExampleProject\\SomeObject->SomeFunction',
                new NonScalarPhpType('ExampleProject', 'SomeObject', '', null, [])
            ],
            [
                'class ExampleProject\\SomeObject::StaticFunction',
                new NonScalarPhpType('ExampleProject', 'SomeObject', '', null, [])
            ],
            [
                'ExampleProject\\SomeClass->ExampleProject\\{closure}',
                new NonScalarPhpType(TracerPhpTypeMapper::NAMESPACE_GLOBAL, 'callable', '', null, [])
            ],
            ['null', new UnresolvablePhpType(UnresolvablePhpType::NONE)]
        ];
    }
}
