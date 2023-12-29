<?php

namespace App\Enum;

enum EnvironmentVariable: string
{
    case WEBHOOK_QUEUE = 'WEBHOOK_QUEUE';
    case GITHUB_APP_ID = 'GITHUB_APP_ID';
    case WEBHOOK_SECRET = 'WEBHOOK_SECRET';
}
