<?php

declare(strict_types=1);

namespace App\Model\Webhook\Github;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookType;
use App\Model\Webhook\AbstractWebhook;
use App\Model\Webhook\PipelineStateChangeWebhookInterface;
use App\Model\Webhook\SignedWebhookInterface;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Enum\JobState;
use Symfony\Component\Serializer\Annotation\SerializedPath;

/**
 * A webhook received from GitHub about a check run.
 *
 * @see https://docs.github.com/en/webhooks/webhook-events-and-payloads#check_run
 */
final class GithubCheckRunWebhook extends AbstractWebhook implements
    PipelineStateChangeWebhookInterface,
    SignedWebhookInterface
{
    /**
     * In GitHub webhooks, a null commit has is occasionally represented by a series of 0's.
     */
    private const string NULL_COMMIT = '0000000000000000000000000000000000000000';

    public function __construct(
        private readonly ?string $signature,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string|int $externalId,
        private readonly string|int $appId,
        private readonly string $ref,
        private readonly string $commit,
        private readonly ?string $parent,
        private readonly string|int|null $pullRequest,
        private readonly ?string $baseRef,
        private readonly ?string $baseCommit,
        private readonly JobState $jobState,
        private readonly DateTimeImmutable $startedAt,
        private readonly ?DateTimeImmutable $completedAt,
    ) {
    }

    #[Override]
    public function getProvider(): Provider
    {
        return Provider::GITHUB;
    }

    #[SerializedPath('[repository][owner][login]')]
    #[Override]
    public function getOwner(): string
    {
        return $this->owner;
    }

    #[SerializedPath('[repository][name]')]
    #[Override]
    public function getRepository(): string
    {
        return $this->repository;
    }

    #[Override]
    public function getSignature(): ?string
    {
        return $this->signature;
    }

    #[Override]
    public function getType(): WebhookType
    {
        return WebhookType::GITHUB_CHECK_RUN;
    }

    #[SerializedPath('[check_run][id]')]
    #[Override]
    public function getExternalId(): string|int
    {
        return $this->externalId;
    }

    #[SerializedPath('[check_run][app][id]')]
    #[Override]
    public function getAppId(): string
    {
        return (string)$this->appId;
    }

    #[SerializedPath('[check_run][status]')]
    #[Override]
    public function getJobState(): JobState
    {
        return $this->jobState;
    }

    #[SerializedPath('[check_run][check_suite][head_branch]')]
    #[Override]
    public function getRef(): string
    {
        return $this->ref;
    }

    #[SerializedPath('[check_run][check_suite][head_sha]')]
    #[Override]
    public function getCommit(): string
    {
        return $this->commit;
    }

    #[SerializedPath('[check_run][check_suite][before]')]
    #[Override]
    public function getParent(): ?string
    {
        if ($this->parent === self::NULL_COMMIT) {
            // Generally this happens when the event occurs on a merged pull request.
            return null;
        }

        return $this->parent;
    }

    #[SerializedPath('[check_run][pull_requests][0][number]')]
    #[Override]
    public function getPullRequest(): string|int|null
    {
        return $this->pullRequest;
    }

    #[SerializedPath('[check_run][pull_requests][0][base][ref]')]
    #[Override]
    public function getBaseRef(): ?string
    {
        return $this->baseRef;
    }

    #[SerializedPath('[check_run][pull_requests][0][base][sha]')]
    #[Override]
    public function getBaseCommit(): ?string
    {
        return $this->baseCommit;
    }

    #[SerializedPath('[check_run][started_at]')]
    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    #[SerializedPath('[check_run][completed_at]')]
    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    #[Override]
    public function getEventTime(): DateTimeImmutable
    {
        return $this->completedAt ?? $this->startedAt;
    }

    /**
     * A per-commit message group for check runs means that the Webhook processor
     * is able to confidently run through the queue messages knowing the messages
     * are only relevant to the specific commit
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
                    $this->commit
                ]
            )
        );
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            'GithubCheckRunWebhook#%s-%s-%s-%s-%s',
            $this->getProvider()->value,
            $this->owner,
            $this->repository,
            $this->externalId,
            $this->commit
        );
    }

    #[Override]
    public function getEvent(): WebhookProcessorEvent
    {
        return WebhookProcessorEvent::JOB_STATE_CHANGE;
    }
}
