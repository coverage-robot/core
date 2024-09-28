<?php

namespace Packages\Configuration\Mock;

use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Override;
use Packages\Contracts\Environment\Service;

class MockEnvironmentService implements EnvironmentServiceInterface
{
    public function __construct(
        private readonly Environment $environment,
        private readonly Service $service,
        private readonly array $variables = []
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

    #[Override]
    public function getVariable($name): string
    {
        return $this->variables[$name->value] ?? '';
    }
}
