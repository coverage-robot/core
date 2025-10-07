<?php

declare(strict_types=1);

namespace Packages\Configuration\Service;

use BackedEnum;
use Override;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Environment\Service;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class EnvironmentService implements EnvironmentServiceInterface
{
    public function __construct(
        private KernelInterface $kernel,
        #[Autowire(value: '%configuration.service%')]
        private Service $service,
    ) {
    }

    #[Override]
    public function getEnvironment(): Environment
    {
        return Environment::from($this->kernel->getEnvironment());
    }

    #[Override]
    public function getService(): Service
    {
        return $this->service;
    }

    /**
     * @param BackedEnum $variable
     */
    #[Override]
    public function getVariable(mixed $variable): string
    {
        $variable = (string)$variable->value;

        if (array_key_exists($variable, $_ENV)) {
            return (string)$_ENV[$variable];
        }

        $envVar  = getenv($variable);

        if (false === $envVar) {
            return '';
        }

        return $envVar;
    }
}
