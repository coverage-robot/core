<?php

namespace App\Enum;

use App\Service\Publisher\Github\GithubCheckRunPublisherService;
use App\Service\Publisher\Github\GithubPullRequestCommentPublisherService;

enum TemplateVariant: string
{
    /**
     * Load the title template for annotations.
     *
     * @see GithubCheckRunPublisherService
     */
    case TITLE = 'title';

    /**
     * Load the complete template for Pull Requests, Check Runs or
     * Annotations.
     *
     * @see GithubPullRequestCommentPublisherService
     */
    case COMPLETE = 'complete';

    /**
     * Load the in progress template for Pull Requests or Check Runs.
     *
     * @see GithubCheckRunPublisherService
     */
    case IN_PROGRESS = 'in_progress';
}
