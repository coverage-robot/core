<?php

namespace App\Enum;

enum EnvironmentVariable: string
{
    case GITHUB_APP_ID = 'GITHUB_APP_ID';
    case GITHUB_BOT_ID = 'GITHUB_BOT_ID';
}
