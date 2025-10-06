<?php

declare(strict_types=1);

namespace Packages\Configuration\Mock;

use BackedEnum;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Override;
use Packages\Contracts\Environment\Service;

final class MockEnvironmentService implements EnvironmentServiceInterface
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

    /**
     * @param BackedEnum $variable
     */
    #[Override]
    public function getVariable(mixed $variable): string
    {
        return $this->variables[$variable->value] ?? '';
    }
}
