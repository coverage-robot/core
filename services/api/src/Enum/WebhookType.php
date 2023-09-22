<?php

namespace App\Enum;

enum WebhookType: string
{
    case GITHUB_CHECK_RUN = 'github_check_run';
}
