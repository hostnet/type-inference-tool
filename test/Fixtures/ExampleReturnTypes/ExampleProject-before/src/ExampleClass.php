<?php
declare(strict_types = 1);

namespace ExampleProject\Component;

abstract class ExampleClass
{
    public function singleLineFunc()
    {
        return 'Hello world';
    }

    public function multiLineFunc(
        $arg0,
        $arg1
    ) {
        return $arg0 . $arg1;
    }

    abstract public function abstractFunction($arg);
}
