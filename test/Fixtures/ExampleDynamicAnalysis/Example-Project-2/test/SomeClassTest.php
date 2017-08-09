<?php
declare(strict_types = 1);

namespace ExampleProject2;

use PHPUnit\Framework\TestCase;

class SomeClassTest extends TestCase
{
    public function testFooWithArguments()
    {
        $test = new SomeClass();
        self::assertNotNull($test->foo(123, new SomeClass()));
    }

    public function testFooWithoutArguments()
    {
        $test = new SomeClass();
        self::assertNull($test->foo(null, null));
    }
}
