<?php

namespace App\Model\Webhook\Github;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookType;
use App\Model\Webhook\AbstractWebhook;
use App\Model\Webhook\PipelineStateChangeWebhookInterface;
use App\Model\Webhook\SignedWebhookInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Enum\JobState;
use Symfony\Component\Serializer\Annotation\SerializedPath;

class GithubCheckRunWebhook extends AbstractWebhook implements
    PipelineStateChangeWebhookInterface,
    SignedWebhookInterface
{
    public function __construct(
        private readonly ?string $signature,
        protected readonly string $owner,
        protected readonly string $repository,
        protected readonly string|int $externalId,
        protected readonly string|int $appId,
        protected readonly string $ref,
        protected readonly string $commit,
        protected readonly string $parent,
        protected readonly string|int|null $pullRequest,
        protected readonly ?string $baseRef,
        protected readonly ?string $baseCommit,
        protected readonly JobState $jobState
    ) {
    }

    public function getProvider(): Provider
    {
        return Provider::GITHUB;
    }

    #[SerializedPath('[repository][owner][login]')]
    public function getOwner(): string
    {
        return $this->owner;
    }

    #[SerializedPath('[repository][name]')]
    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function getType(): WebhookType
    {
        return WebhookType::GITHUB_CHECK_RUN;
    }

    #[SerializedPath('[check_run][id]')]
    public function getExternalId(): string|int
    {
        return $this->externalId;
    }

    #[SerializedPath('[check_run][app][id]')]
    public function getAppId(): int|string
    {
        return (string)$this->appId;
    }

    #[SerializedPath('[check_run][status]')]
    public function getJobState(): JobState
    {
        return $this->jobState;
    }

    #[SerializedPath('[check_run][check_suite][head_branch]')]
    public function getRef(): string
    {
        return $this->ref;
    }

    #[SerializedPath('[check_run][check_suite][head_sha]')]
    public function getCommit(): string
    {
        return $this->commit;
    }

    #[SerializedPath('[check_run][check_suite][before]')]
    public function getParent(): string
    {
        return $this->parent;
    }

    #[SerializedPath('[check_run][pull_requests][0][number]')]
    public function getPullRequest(): string|int|null
    {
        return $this->pullRequest;
    }

    #[SerializedPath('[check_run][pull_requests][0][base][ref]')]
    public function getBaseRef(): ?string
    {
        return $this->baseRef;
    }

    #[SerializedPath('[check_run][pull_requests][0][base][sha]')]
    public function getBaseCommit(): ?string
    {
        return $this->baseCommit;
    }

    /**
     * A per-commit message group for check runs means that the Webhook processor
     * is able to confidently run through the queue messages knowing the messages
     * are only relevant to the specific commit
     */
    public function getMessageGroup(): string
    {
        return md5(
            implode(
                '',
                [
                    $this->getProvider()->value,
                    $this->getOwner(),
                    $this->getRepository(),
                    $this->getCommit()
                ]
            )
        );
    }

    public function __toString(): string
    {
        return sprintf(
            'GithubCheckRunWebhook#%s-%s-%s-%s-%s',
            $this->getProvider()->value,
            $this->getOwner(),
            $this->getRepository(),
            $this->getExternalId(),
            $this->getCommit()
        );
    }

    public function getEvent(): WebhookProcessorEvent
    {
        return WebhookProcessorEvent::JOB_STATE_CHANGE;
    }
}
