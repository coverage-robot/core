<?php

namespace App\Enum;

enum EnvironmentVariable: string
{
    case EVENT_STORE = 'EVENT_STORE';

    case GITHUB_APP_ID = 'GITHUB_APP_ID';
}
