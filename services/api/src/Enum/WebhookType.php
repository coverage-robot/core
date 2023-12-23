<?php

namespace App\Enum;

enum WebhookType: string
{
    /**
     * A check run has been created or updated.
     */
    case GITHUB_CHECK_RUN = 'github_check_run';

    /**
     * A new push to a repository has occurred.
     */
    case GITHUB_PUSH = 'github_push';
}
