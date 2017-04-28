<?php
declare(strict_types = 1);

namespace ExampleProject;

use PHPUnit\Framework\TestCase;

class SomeClassTest extends TestCase
{
    /**
     * @var SomeClass
     */
    private $some_class;

    /**
     * @var SomeObject
     */
    private $some_object;

    /**
     * @var A\SomeObject
     */
    private $some_object_2;

    /**
     * @var \ArrayObject
     */
    private $global;

    private $string = 'Some value';
    private $int    = 12;
    private $float  = 3.45;
    private $bool   = true;
    private $array  = ['1', '2'];

    protected function setUp()
    {
        $this->some_object   = new SomeObject(123);
        $this->some_object_2 = new A\SomeObject(123);
        $this->global        = new \ArrayObject();
        $this->some_class    = new SomeClass(
            $this->string,
            $this->int,
            $this->float,
            $this->bool,
            $this->bool,
            $this->int,
            $this->some_object,
            $this->some_object_2,
            $this->array,
            $this->global
        );
    }

    public function testSomeClassConstructorSetsCorrectValues()
    {
        self::assertSame($this->string, $this->some_class->getString());
        self::assertSame($this->int, $this->some_class->getInt());
        self::assertSame($this->float, $this->some_class->getFloat());
        self::assertSame($this->bool, $this->some_class->getBool());
        self::assertSame($this->bool, $this->some_class->getInconsistent());
        self::assertSame($this->some_object, $this->some_class->getSomeObject());
        self::assertSame($this->some_object_2, $this->some_class->getSomeObject2());
        self::assertSame($this->array, $this->some_class->getArray());
        self::assertSame($this->global, $this->some_class->getGlobal());
    }

    public function testSomeClassConstructorWithInconsistentArgumentType()
    {
        $inconsistent_type = 'Some string';
        $some_class        = new SomeClass(
            $this->string,
            $this->int,
            $this->float,
            $this->bool,
            $inconsistent_type,
            $this->int,
            new SomeObject(567),
            $this->some_object_2,
            $this->array,
            $this->global
        );

        self::assertSame($inconsistent_type, $some_class->getInconsistent());
    }

    public function testDoSomethingWithALotOfParameters()
    {
        self::assertSame('abcdef', $this->some_class->doSomethingWithALotOfParameters('a', 'b', 'c', 'd', 'e', 'f'));
    }

    public function testSetters()
    {

        $some_class = $this->some_class;
        $some_class->setArray($this->array);
        $some_class->setBool($this->bool);
        $some_class->setFloat($this->float);
        $some_class->setGlobal($this->global);
        $some_class->setInconsistent($this->global);
        $some_class->setInconsistent($this->int);
        $some_class->setInt($this->int);
        $some_class->setSomeObject(new SomeObject($this->float));
        $some_class->setString($this->string);
        $some_class->setTypedInt($this->int);

        self::assertSame($this->int, $some_class->getInconsistent());
    }

    public function testSomethingWithMocks()
    {
        $some_object_mock = $this->prophesize(SomeObject::class);
        $some_class       = $this->some_class;
        $some_class->setSomeObject($some_object_mock->reveal());

        self::assertNotNull($some_class->getSomeObject());
    }

    public function testReturnAndSetMixedIntAndFloat()
    {
        self::assertSame($this->float, SomeClass::returnsMixedIntAndFloat($this->float));
        self::assertSame($this->int, SomeClass::returnsMixedIntAndFloat($this->int));
    }

    public function testFooWithDefaultParameter()
    {
        self::assertSame('Hello', SomeClass::fooWithDefaultParameter());
    }

    public function testImplementedMethod()
    {
        self::assertSame($this->string, $this->some_class->foobar($this->string));
    }
}