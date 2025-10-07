<?php

declare(strict_types=1);

namespace Packages\Configuration\Mock;

use Packages\Configuration\Service\SettingServiceInterface;

final class MockSettingServiceFactory
{
    public static function createMock(array $settings = []): SettingServiceInterface
    {
        return new MockSettingService($settings);
    }
}
