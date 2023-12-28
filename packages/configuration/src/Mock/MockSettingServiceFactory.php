<?php

namespace Packages\Configuration\Mock;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Service\SettingService;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockSettingServiceFactory
{
    public static function getMock(
        TestCase $test,
        $settings = []
    ): SettingService|MockObject {
        $mockSettingService = $test->getMockBuilder(SettingService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockSettingService->method('get')
            ->willReturnCallback(
                static fn (
                    Provider $provider,
                    string $owner,
                    string $repository,
                    SettingKey $key
                ) => $settings[$key->value] ?? null
            );

        return $mockSettingService;
    }
}
