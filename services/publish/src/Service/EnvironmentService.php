<?php

namespace App\Service;

use App\Enum\EnvironmentVariable;
use App\Kernel;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;

class EnvironmentService implements EnvironmentServiceInterface
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function getEnvironment(): Environment
    {
        return Environment::from($this->kernel->getEnvironment());
    }

    /**
     * @param EnvironmentVariable $variable
     */
    public function getVariable($variable): string
    {
        return (string)($_ENV[$variable->value] ?: getenv($variable->value));
    }
}
