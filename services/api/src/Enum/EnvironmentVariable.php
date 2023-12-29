<?php

namespace App\Enum;

enum EnvironmentVariable: string
{
    case GITHUB_APP_ID = 'GITHUB_APP_ID';
    case WEBHOOK_SECRET = 'WEBHOOK_SECRET';
}
