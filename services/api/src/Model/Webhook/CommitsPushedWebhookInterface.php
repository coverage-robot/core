<?php

namespace App\Model\Webhook;

interface CommitsPushedWebhookInterface
{
    /**
     * The ref the push occurred on.
     */
    public function getRef(): string;

    /**
     * The commit hash at the head of the push.
     */
    public function getHeadCommit(): string;

    /**
     * The list of commits which were pushed.
     *
     * @return PushedCommitInterface[]
     */
    public function getCommits(): array;
}
