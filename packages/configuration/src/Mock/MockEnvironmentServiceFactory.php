<?php

namespace Packages\Configuration\Mock;

use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MockEnvironmentServiceFactory
{
    public static function createMock(
        TestCase $testCase,
        Environment $environment,
        array $variables = []
    ): EnvironmentServiceInterface|MockObject {
        $mockEnvironmentService = $testCase->getMockBuilder(EnvironmentServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockEnvironmentService->method('getEnvironment')
            ->willReturn($environment);

        $mockEnvironmentService->method('getVariable')
            ->willReturnCallback(static fn ($variableName) => $variables[$variableName->value] ?? null);

        return $mockEnvironmentService;
    }
}
