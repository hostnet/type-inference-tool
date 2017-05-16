<?php
declare(strict_types = 1);

namespace ExampleStaticProject;

abstract class AbstractFoo
{
    abstract public function doSomething();

    protected function foobar()
    {
        return 'Hello';
    }
}
