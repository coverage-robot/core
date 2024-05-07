<?php

namespace Packages\Configuration\Service;

use Override;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class EnvironmentService implements EnvironmentServiceInterface
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    #[Override]
    public function getEnvironment(): Environment
    {
        return Environment::from($this->kernel->getEnvironment());
    }

    #[Override]
    public function getVariable($variable): string
    {
        if (array_key_exists($variable->value, $_ENV)) {
            return (string)$_ENV[$variable->value];
        }

        return getenv($variable->value) ?: '';
    }
}
