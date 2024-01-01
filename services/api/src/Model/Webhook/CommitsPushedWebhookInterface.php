<?php

namespace App\Model\Webhook;

use Symfony\Component\Validator\Constraints as Assert;

interface CommitsPushedWebhookInterface
{
    /**
     * The ref the push occurred on.
     */
    #[Assert\NotBlank]
    public function getRef(): string;

    /**
     * The commit hash at the head of the push.
     */
    #[Assert\NotBlank]
    public function getHeadCommit(): string;

    /**
     * The list of commits which were pushed.
     *
     * @return PushedCommitInterface[]
     */
    #[Assert\All([
        new Assert\Type(PushedCommitInterface::class),
    ])]
    public function getCommits(): array;
}
