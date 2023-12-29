<?php

namespace Packages\Contracts\Environment;

use StringBackedEnum;

interface EnvironmentServiceInterface
{
    /**
     * Get the environment which the service is configured as running in.
     */
    public function getEnvironment(): Environment;

    /**
     * Get the value of an environment variable.
     *
     * @param StringBackedEnum $variable
     */
    public function getVariable($variable): string;
}
