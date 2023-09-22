<?php

namespace App\Model\Webhook\Github;

use App\Enum\JobState;
use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookType;
use App\Model\Webhook\AbstractWebhook;
use App\Model\Webhook\PipelineStateChangeWebhookInterface;
use App\Model\Webhook\SignedWebhookInterface;
use Packages\Models\Enum\Provider;
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
        protected readonly string $ref,
        protected readonly string $commit,
        protected readonly ?string $pullRequest,
        protected readonly JobState $jobState,
        protected readonly JobState $suiteState,
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

    #[SerializedPath('[check_run][check_suite][status]')]
    public function getSuiteState(): JobState
    {
        return $this->suiteState;
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

    #[SerializedPath('[check_run][pull_requests][0][number]')]
    public function getPullRequest(): ?string
    {
        return $this->pullRequest;
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
        return WebhookProcessorEvent::PIPELINE_STATE_CHANGE;
    }
}
