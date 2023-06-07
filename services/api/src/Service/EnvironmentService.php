<?php

namespace App\Service;

use App\Kernel;
use Packages\Models\Enum\Environment;

class EnvironmentService
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function getEnvironment(): Environment
    {
        return Environment::from($this->kernel->getEnvironment());
    }
}
