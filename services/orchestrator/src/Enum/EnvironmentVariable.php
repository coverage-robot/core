<?php

declare(strict_types=1);

namespace App\Enum;

enum EnvironmentVariable: string
{
    case EVENT_STORE = 'EVENT_STORE';

    case GITHUB_APP_ID = 'GITHUB_APP_ID';
}
