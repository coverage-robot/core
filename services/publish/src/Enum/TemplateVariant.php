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
    case ANNOTATION_TITLE = 'annotation_title';

    /**
     * Load the annotation template for annotations.
     *
     * @see GithubCheckRunPublisherService
     */
    case ANNOTATION_BODY = 'annotation_body';

    /**
     * Load the complete template for Pull Requests, Check Runs or
     * Annotations.
     *
     * @see GithubPullRequestCommentPublisherService
     */
    case FULL_PULL_REQUEST_COMMENT = 'full_pull_request_comment';

    /**
     * Load the complete template for Check Runs.
     */
    case COMPLETE_CHECK_RUN = 'complete_check_run';

    /**
     * Load the in progress template for Pull Requests or Check Runs.
     *
     * @see GithubCheckRunPublisherService
     */
    case IN_PROGRESS_CHECK_RUN = 'in_progress_check_run';
}
