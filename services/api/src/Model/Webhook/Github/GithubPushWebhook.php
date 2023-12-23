<?php

namespace App\Model\Webhook\Github;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookType;
use App\Model\Webhook\AbstractWebhook;
use App\Model\Webhook\CommitsPushedWebhookInterface;
use App\Model\Webhook\SignedWebhookInterface;
use Override;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Annotation\SerializedPath;

/**
 * A webhook received from GitHub about a push to the repository.
 *
 * @see https://docs.github.com/en/webhooks/webhook-events-and-payloads#push
 */
class GithubPushWebhook extends AbstractWebhook implements
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
    public function getRef(): string
    {
        return $this->ref;
    }

    #[SerializedPath('[after]')]
    public function getHeadCommit(): string
    {
        return $this->headCommit;
    }

    #[SerializedPath('[commits]')]
    public function getCommits(): array
    {
        return $this->commits;
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
                    $this->getOwner(),
                    $this->getRepository(),
                    $this->getRef()
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
            $this->getOwner(),
            $this->getRepository(),
            $this->getRef()
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
