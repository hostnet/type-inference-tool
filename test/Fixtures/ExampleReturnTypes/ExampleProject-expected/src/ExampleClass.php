<?php
declare(strict_types = 1);

namespace ExampleProject\Component;

class ExampleClass
{
    public function singleLineFunc(): string
    {
        return 'Hello world';
    }

    public function multiLineFunc(
        $arg0,
        $arg1
    ): string {
        return $arg0 . $arg1;
    }
}
