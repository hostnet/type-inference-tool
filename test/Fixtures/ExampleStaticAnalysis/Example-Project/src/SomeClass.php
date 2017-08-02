<?php
declare(strict_types = 1);

namespace ExampleStaticProject;

use ExampleStaticProject\AbstractFoo;
use ExampleStaticProject\FooInterface;

class SomeClass extends AbstractFoo implements FooInterface
{
    private $foo;

    public function __construct($foo)
    {
        $this->foo = $foo;
    }

    public function getFoo()
    {
        // Overridden from FooInterface

        return $this->foo;
    }

    public function doSomething()
    {
        // Overridden from AbstractFoo

        return $this->foobar();
    }
}
