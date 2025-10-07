<?php

declare(strict_types=1);

namespace Packages\Configuration\Mock;

use BackedEnum;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Override;
use Packages\Contracts\Environment\Service;

final readonly class MockEnvironmentService implements EnvironmentServiceInterface
{
    /**
     * @param array<array-key, string> $variables
     */
    public function __construct(
        private Environment $environment,
        private Service $service,
        private array $variables = []
    ) {
    }

    #[Override]
    public function getEnvironment(): Environment
    {
        return $this->environment;
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
        return $this->variables[$variable->value] ?? '';
    }
}
