<?php

namespace Packages\Configuration\Mock;

use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Override;

class MockEnvironmentService implements EnvironmentServiceInterface
{
    public function __construct(
        private readonly Environment $environment,
        private readonly array $variables = []
    ) {
    }

    #[Override]
    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    #[Override]
    public function getVariable($name): string
    {
        return $variables[$name->value] ?? '';
    }
}
