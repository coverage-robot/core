<?php

namespace Packages\Configuration\Mock;

use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MockEnvironmentServiceFactory
{
    public static function createMock(
        Environment $environment,
        array $variables = []
    ): EnvironmentServiceInterface {
        return new MockEnvironmentService($environment, $variables);
    }
}