<?php

namespace Packages\Configuration\Mock;

use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;

class MockEnvironmentService implements EnvironmentServiceInterface
{
    public function __construct(
        private readonly Environment $environment,
        private readonly array $variables = []
    ) {    
    }

	public function getEnvironment(): Environment
	{
        return $this->environment;
	}

	public function getVariable($name): string
	{
        return $variables[$name] ?? "";
	}
}
