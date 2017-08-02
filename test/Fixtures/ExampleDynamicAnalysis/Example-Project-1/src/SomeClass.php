<?php
declare(strict_types = 1);

namespace ExampleProject;

class SomeClass extends ShouldBeIgnored implements SomeClassInterface
{
    private $string;
    private $int;
    private $float;
    private $bool;
    private $inconsistent;
    private $typed_int;
    private $some_object;
    private $some_object_2;
    private $array;
    private $global;

    public function __construct($string, $int, $float, $bool, $inconsistent, int $typed_int, $user_defined_object, $obj2, &$array, $global)
    {
        $this->string        = $string;
        $this->int           = $int;
        $this->float         = $float;
        $this->bool          = $bool;
        $this->inconsistent  = $inconsistent;
        $this->typed_int     = $typed_int;
        $this->some_object   = $user_defined_object;
        $this->some_object_2 = $obj2;
        $this->array         = $array;
        $this->global        = $global;
    }

    public function doSomethingWithALotOfParameters(
        $arg0,
        string $arg1,
        $arg2,
        $arg3,
        $arg4,
        $arg5
    ) {
        return $arg0 . $arg1 . $arg2 . $arg3 . $arg4 . $arg5;
    }

    public function getString()
    {
        return $this->string;
    }

    public function setString($string)
    {
        $this->string = $string;
    }

    public function getInt(): int
    {
        return $this->int;
    }

    public function setInt(int $int)
    {
        $this->int = $int;
    }

    public function getFloat()
    {
        return $this->float;
    }

    public function setFloat($float)
    {
        $this->float = $float;
    }

    public function getBool()
    {
        return $this->bool;
    }

    public function setBool($bool)
    {
        $this->bool = $bool;
    }

    public function getInconsistent()
    {
        return $this->inconsistent;
    }

    public function setInconsistent($inconsistent)
    {
        $this->inconsistent = $inconsistent;
    }

    public function getTypedInt(): int
    {
        return $this->typed_int;
    }

    public function setTypedInt(int $typed_int)
    {
        $this->typed_int = $typed_int;
    }

    public function getSomeObject()
    {
        return $this->doSomethingWithSomeObject($this->some_object);
    }

    public function doSomethingWithSomeObject($some_object)
    {
        // Do something ...
        return $some_object;
    }

    public function setSomeObject($some_object)
    {
        $this->some_object = $some_object;
    }

    public function getSomeObject2()
    {
        return $this->some_object_2;
    }

    public function getArray()
    {
        return $this->array;
    }

    public function setArray($array)
    {
        $this->array = $array;
    }

    public function getGlobal()
    {
        return $this->global;
    }

    public function setGlobal($global)
    {
        $this->global = $global;
    }

    public static function returnsMixedIntAndFloat($int_or_float)
    {
        return $int_or_float;
    }

    public static function fooWithDefaultParameter($arg = 'Hello')
    {
        return $arg;
    }

    public function foobar($arg)
    {
        $util = new Util();
        $util->doCalculation();
        return $arg;
    }

    /**
     * {@inheritdoc}
     */
    public static function getValue($int)
    {
        return 6 + $int;
    }
}