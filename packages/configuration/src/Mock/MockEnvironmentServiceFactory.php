<?php

declare(strict_types=1);

namespace Packages\Configuration\Mock;

use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Environment\Service;

final class MockEnvironmentServiceFactory
{
    /**
     * @param array<array-key, string> $variables
     */
    public static function createMock(
        Environment $environment,
        Service $service,
        array $variables = []
    ): MockEnvironmentService {
        return new MockEnvironmentService($environment, $service, $variables);
    }
}
