<?php

namespace App\Model\Webhook;

use App\Enum\JobState;

interface PipelineStateChangeWebhookInterface
{
    /**
     * The ref the job is running on.
     */
    public function getRef(): string;

    /**
     * The commit the job is running on.
     */
    public function getCommit(): string;

    /**
     * The unique identifier of the job which is running.
     *
     * For example, the check run id for GitHub.
     */
    public function getExternalId(): string|int;

    /**
     * The current state of the suite of jobs the job is in.
     */
    public function getSuiteState(): JobState;

    /**
     * The current state of the job.
     */
    public function getJobState(): JobState;

    /**
     * The pull request the job is running on (if applicable)
     */
    public function getPullRequest(): string|int|null;
}
