<?php
declare(strict_types = 1);

namespace ExampleProject;

class SomeObject
{
    private $mixed_int_float;

    public function __construct($mixed_int_float)
    {
        $this->mixed_int_float = $mixed_int_float;

        $this->executeCallback(function () {
            // This is a callable
        });
    }

    private function executeCallback($cb)
    {
        $cb();
    }
}