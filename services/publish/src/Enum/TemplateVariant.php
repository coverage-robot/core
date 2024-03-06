<?php

namespace App\Enum;

use App\Service\Publisher\Github\GithubAnnotationPublisherService;
use App\Service\Publisher\Github\GithubCheckRunPublisherService;
use App\Service\Publisher\Github\GithubPullRequestCommentPublisherService;
use App\Service\Publisher\Github\GithubReviewPublisherService;

enum TemplateVariant: string
{
    /**
     * Load the line comment titles template for annotations.
     *
     *  @see GithubAnnotationPublisherService
     */
    case LINE_COMMENT_TITLE = 'line_comment_title';

    /**
     * Load the line comment body template for reviews.
     *
     * @see GithubAnnotationPublisherService
     * @see GithubReviewPublisherService
     */
    case LINE_COMMENT_BODY = 'line_comment_body';

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

    /**
     * Load the template for a failed check run.
     */
    case FAILED_CHECK_RUN = 'failed_check_run';
}
