<?php

namespace Packages\Contracts\Environment;

interface EnvironmentServiceInterface
{
    /**
     * Get the environment which the service is configured as running in.
     */
    public function getEnvironment(): Environment;

    /**
     * Get the name of the service which the code is running in.
     */
    public function getService(): Service;

    /**
     * Get the value of an environment variable.
     */
    public function getVariable($variable): string;
}
