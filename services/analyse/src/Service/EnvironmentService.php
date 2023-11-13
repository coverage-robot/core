<?php

namespace App\Service;

use App\Enum\EnvironmentVariable;
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

    public function getVariable(EnvironmentVariable $variable): string
    {
        return $_ENV[$variable->value] ?? getenv($variable->value);
    }
}
