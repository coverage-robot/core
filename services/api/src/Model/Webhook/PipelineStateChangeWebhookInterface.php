<?php

namespace App\Model\Webhook;

use Packages\Event\Enum\JobState;

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
     * The parent commit of the commit the job is running on.
     */
    public function getParent(): string;

    /**
     * The unique identifier of the job which is running.
     *
     * For example, the check run id for GitHub.
     */
    public function getExternalId(): string|int;

    /**
     * The ID of the application which triggered the state change.
     *
     * Mainly relevant for identifying if the state change is from
     * _us_ (and thus can be ignored).
     */
    public function getAppId(): int|string;

    /**
     * The current state of the job.
     */
    public function getJobState(): JobState;

    /**
     * The pull request the job is running on (if applicable)
     */
    public function getPullRequest(): string|int|null;

    /**
     * The ref the pull request is based on (if applicable)
     */
    public function getBaseRef(): ?string;

    /**
     * The commit the pull request is based on (if applicable)
     */
    public function getBaseCommit(): ?string;
}
