<?php

namespace Packages\Configuration\Mock;

use Packages\Configuration\Service\SettingServiceInterface;

final class MockSettingServiceFactory
{
    public static function createMock($settings = []): SettingServiceInterface
    {

        return new MockSettingService($settings);
    }
}
