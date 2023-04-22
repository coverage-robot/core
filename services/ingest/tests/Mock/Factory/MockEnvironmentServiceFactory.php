<?php

namespace App\Tests\Mock\Factory;

use App\Enum\EnvironmentEnum;
use App\Service\EnvironmentService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockEnvironmentServiceFactory
{
    public static function getMock(TestCase $testCase, EnvironmentEnum $environment): EnvironmentService&MockObject {
        $mockEnvironmentService = $testCase->getMockBuilder(EnvironmentService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockEnvironmentService->method("isDevelopmentEnvironment")
            ->willReturn($environment === EnvironmentEnum::DEVELOPMENT);

        $mockEnvironmentService->method("getEnvironment")
            ->willReturn($environment);

        return $mockEnvironmentService;
    }
}