<?php

declare(strict_types=1);

namespace Packages\Configuration\Mock;

final class MockSettingServiceFactory
{
    public static function createMock(array $settings = []): MockSettingService
    {
        return new MockSettingService($settings);
    }
}
