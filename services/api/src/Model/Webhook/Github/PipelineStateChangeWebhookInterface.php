<?php

namespace App\Model\Webhook\Github;

use App\Enum\JobState;

interface PipelineStateChangeWebhookInterface
{
    /**
     * The commit the job is running on.
     */
    public function getCommit(): string;

    /**
     * The unique identifier of the job which is running.
     *
     * For example, the check run id for GitHub.
     */
    public function getExternalId(): string;

    /**
     * The current state of the job.
     */
    public function getJobState(): JobState;

    /**
     * The pull request the job is running on (if applicable)
     */
    public function getPullRequest(): ?string;
}
