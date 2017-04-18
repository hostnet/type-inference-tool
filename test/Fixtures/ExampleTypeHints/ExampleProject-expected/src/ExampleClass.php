<?php
declare(strict_types = 1);

namespace ExampleProject\Component;

class ExampleClass
{
    public function singleLineFunc(int $number)
    {
        return 'Hello world';
    }

    public function multiLineFunc(
        int $arg0,
        $arg1
    ) {
        return $arg0 . $arg1;
    }
}
