<?php

namespace Packages\Models\Enum;

enum Environment: string
{
    case TESTING = 'test';
    case DEVELOPMENT = 'dev';
    case PRODUCTION = 'prod';
}
