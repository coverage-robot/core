<?php

namespace App\Tests\Mock\Factory;

use App\Enum\EnvironmentVariable;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockEnvironmentServiceFactory
{
    public static function getMock(
        TestCase $testCase,
        Environment $environment,
        array $variables = []
    ): EnvironmentServiceInterface&MockObject {
        $mockEnvironmentService = $testCase->createMock(EnvironmentServiceInterface::class);

        $mockEnvironmentService->method('getEnvironment')
            ->willReturn($environment);

        $mockEnvironmentService->method('getVariable')
            ->willReturnMap(
                array_map(
                    static fn (string $variableName, string $value) => [
                        EnvironmentVariable::from($variableName),
                        $value
                    ],
                    array_keys($variables),
                    array_values($variables)
                )
            );

        return $mockEnvironmentService;
    }
}
