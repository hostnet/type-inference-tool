<?php
declare(strict_types=1);

namespace ExampleProject;

class Util
{
    public function doCalculation(): int
    {
        return 1 + $this->getNumber();
    }

    private function getNumber(): int
    {
        return 1;
    }
}