<?php

namespace App\Service;

use App\Enum\EnvironmentEnum;

class EnvironmentService
{
    public function isDevelopmentEnvironment(): bool {
        return $_ENV["APP_ENV"] === EnvironmentEnum::DEVELOPMENT;
    }

    public function getEnvironment(): EnvironmentEnum {
        return EnvironmentEnum::from($_ENV["APP_ENV"]);
    }
}