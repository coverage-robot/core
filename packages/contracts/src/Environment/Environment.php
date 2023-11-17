<?php

namespace Packages\Contracts\Environment;

enum Environment: string
{
    /**
     * Services deployed in a development context.
     *
     * This is the local environments, and non-production deployments.
     */
    case DEVELOPMENT = 'dev';

    /**
     * Services run in an automation testing context.
     *
     * This is used for automation testing, and CI pipelines.
     */
    case TESTING = 'test';

    /**
     * Services deployed in a production context.
     *
     * This is the production environment.
     */
    case PRODUCTION = 'prod';
}
