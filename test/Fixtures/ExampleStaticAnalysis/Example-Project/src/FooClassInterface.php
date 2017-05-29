<?php
declare(strict_types = 1);

namespace ExampleStaticProject;

interface FooClassInterface
{
    /**
     * This is a docblock.
     *
     * @param int $number Some input variable
     * @return bool
     */
    public function doFoobar($number);
}
