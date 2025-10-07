<?php

declare(strict_types=1);

namespace Packages\Contracts\Environment;

use BackedEnum;

interface EnvironmentServiceInterface
{
    /**
     * Get the environment which the service is configured as running in.
     */
    public function getEnvironment(): Environment;

    /**
     * Get the service which the environment is configured as running in.
     */
    public function getService(): Service;

    /**
     * Get the value of an environment variable.
     *
     * @param BackedEnum $variable
     */
    public function getVariable(mixed $variable): string;
}
