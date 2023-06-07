<?php

namespace App\Tests\Mock\Factory;

use App\Service\EnvironmentService;
use Packages\Models\Enum\Environment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockEnvironmentServiceFactory
{
    public static function getMock(TestCase $testCase, Environment $environment): EnvironmentService&MockObject
    {
        $mockEnvironmentService = $testCase->getMockBuilder(EnvironmentService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockEnvironmentService->method('getEnvironment')
            ->willReturn($environment);

        return $mockEnvironmentService;
    }
}
