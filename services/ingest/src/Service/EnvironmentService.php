<?php

namespace App\Service;

use App\Enum\EnvironmentEnum;
use App\Kernel;

class EnvironmentService
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function getEnvironment(): EnvironmentEnum
    {
        return EnvironmentEnum::from($this->kernel->getEnvironment());
    }
}
