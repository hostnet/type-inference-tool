<?php
declare(strict_types = 1);

namespace ExampleProject2;

use PHPUnit\Framework\TestCase;

class SomeClassTest extends TestCase
{
    public function testSomething()
    {
        $test = new SomeClass();
        self::assertTrue($test->foo(true));
    }
}
