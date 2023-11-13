<?php

namespace App\Tests\Mock\Factory;

use App\Enum\EnvironmentVariable;
use App\Service\EnvironmentService;
use Packages\Models\Enum\Environment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockEnvironmentServiceFactory
{
    public static function getMock(
        TestCase $testCase,
        Environment $environment,
        array $variables = []
    ): EnvironmentService&MockObject {
        $variables = self::getEnvironmentVariablesWithMockDefaults($variables);

        $mockEnvironmentService = $testCase->getMockBuilder(EnvironmentService::class)
            ->disableOriginalConstructor()
            ->getMock();

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

    private static function getEnvironmentVariablesWithMockDefaults(array $variables): array
    {
        return array_merge(
            [
                EnvironmentVariable::TRACE_ID->value => 'mock-trace-id'
            ],
            $variables
        );
    }
}
