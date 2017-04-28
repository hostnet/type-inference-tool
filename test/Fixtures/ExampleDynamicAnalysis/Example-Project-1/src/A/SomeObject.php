<?php
declare(strict_types = 1);

namespace ExampleProject\A;

class SomeObject
{
    private $number;

    public function __construct($number)
    {
        $this->number = $number;

        $this->executeCallback(function () {
            // This is a callable
        });
    }

    private function executeCallback($cb)
    {
        $cb();
    }
}