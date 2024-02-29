<?php

namespace App\Model\Webhook\Github;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookType;
use App\Model\Webhook\AbstractWebhook;
use App\Model\Webhook\CommitsPushedWebhookInterface;
use App\Model\Webhook\PushedCommitInterface;
use App\Model\Webhook\SignedWebhookInterface;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Annotation\SerializedPath;

/**
 * A webhook received from GitHub about a push to the repository.
 *
 * @see https://docs.github.com/en/webhooks/webhook-events-and-payloads#push
 */
final class GithubPushWebhook extends AbstractWebhook implements
    CommitsPushedWebhookInterface,
    SignedWebhookInterface
{
    /**
     * @param GithubPushedCommit[] $commits
     */
    public function __construct(
        private readonly ?string $signature,
        protected readonly string $owner,
        protected readonly string $repository,
        protected readonly string $ref,
        protected readonly string $headCommit,
        protected readonly array $commits
    ) {
    }

    #[Override]
    public function getProvider(): Provider
    {
        return Provider::GITHUB;
    }

    #[Override]
    #[SerializedPath('[repository][owner][login]')]
    public function getOwner(): string
    {
        return $this->owner;
    }

    #[Override]
    #[SerializedPath('[repository][name]')]
    public function getRepository(): string
    {
        return $this->repository;
    }

    #[SerializedPath('[ref]')]
    #[Override]
    public function getRef(): string
    {
        return $this->ref;
    }

    #[SerializedPath('[after]')]
    #[Override]
    public function getHeadCommit(): string
    {
        return $this->headCommit;
    }

    #[SerializedPath('[commits]')]
    #[Override]
    public function getCommits(): array
    {
        return $this->commits;
    }

    /**
     * Get the time the push happened. Given that a push webhook from GitHub might include
     * more than one commit, the latest commit time (effectively, the commit time of the head
     * commit) is used.
     *
     * In the case that theres no commits (which shouldn't happen), the current time should
     * suffice.
     */
    #[Override]
    public function getEventTime(): DateTimeImmutable
    {
        if ($this->getCommits() === []) {
            return new DateTimeImmutable();
        }

        $commitTimes = array_map(
            static fn (PushedCommitInterface $pushedCommit): DateTimeImmutable => $pushedCommit->getCommittedAt(),
            $this->getCommits()
        );

        return max($commitTimes);
    }

    /**
     * A per-ref message group for pushes means that the Webhook processor is able
     * to confidently run through the queue messages knowing the messages are only
     * relevant to the specific ref
     */
    #[Override]
    public function getMessageGroup(): string
    {
        return md5(
            implode(
                '',
                [
                    $this->getProvider()->value,
                    $this->owner,
                    $this->repository,
                    $this->ref
                ]
            )
        );
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            'GithubPushWebhook#%s-%s-%s-%s',
            $this->getProvider()->value,
            $this->owner,
            $this->repository,
            $this->ref
        );
    }

    #[Override]
    public function getSignature(): ?string
    {
        return $this->signature;
    }

    #[Override]
    public function getType(): WebhookType
    {
        return WebhookType::GITHUB_PUSH;
    }

    #[Override]
    public function getEvent(): WebhookProcessorEvent
    {
        return WebhookProcessorEvent::COMMITS_PUSHED;
    }
}
