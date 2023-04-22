<?php

namespace App\Enum;

enum EnvironmentEnum: string
{
    case TESTING = 'test';
    case DEVELOPMENT = 'dev';
    case PRODUCTION = 'prod';
}
