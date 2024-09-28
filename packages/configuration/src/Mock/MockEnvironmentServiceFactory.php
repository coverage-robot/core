<?php

namespace Packages\Configuration\Mock;

use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Environment\Service;

final class MockEnvironmentServiceFactory
{
    public static function createMock(
        Environment $environment,
        Service $service,
        array $variables = []
    ): EnvironmentServiceInterface {
        return new MockEnvironmentService($environment, $service, $variables);
    }
}
