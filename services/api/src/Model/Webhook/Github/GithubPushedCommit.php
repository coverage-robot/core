<?php

namespace App\Model\Webhook\Github;

use App\Model\Webhook\PushedCommitInterface;
use DateTimeImmutable;
use Override;
use Symfony\Component\Serializer\Annotation\SerializedPath;

/**
 * A webhook received from GitHub about a push to the repository.
 *
 * @see https://docs.github.com/en/webhooks/webhook-events-and-payloads#push
 */
class GithubPushedCommit implements PushedCommitInterface
{
    /**
     * @param string[] $addedFiles
     * @param string[] $modifiedFiles
     * @param string[] $deletedFiles
     */
    public function __construct(
        protected readonly string $commit,
        protected readonly array $addedFiles,
        protected readonly array $modifiedFiles,
        protected readonly array $deletedFiles,
        private readonly DateTimeImmutable $committedAt
    ) {
    }

    #[SerializedPath('[id]')]
    #[Override]
    public function getCommit(): string
    {
        return $this->commit;
    }

    #[SerializedPath('[added]')]
    #[Override]
    public function getAddedFiles(): array
    {
        return $this->addedFiles;
    }

    #[SerializedPath('[modified]')]
    #[Override]
    public function getModifiedFiles(): array
    {
        return $this->modifiedFiles;
    }


    #[SerializedPath('[removed]')]
    #[Override]
    public function getDeletedFiles(): array
    {
        return $this->deletedFiles;
    }

    #[SerializedPath('[timestamp]')]
    #[Override]
    public function getCommittedAt(): DateTimeImmutable
    {
        return $this->committedAt;
    }
}
