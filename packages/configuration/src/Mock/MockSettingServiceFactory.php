<?php

namespace Packages\Configuration\Mock;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Service\SettingService;
use Packages\Configuration\Service\SettingServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MockSettingServiceFactory
{
    public static function createMock($settings = []): SettingServiceInterface
    {
        return new MockSettingService($settings);
    }
}