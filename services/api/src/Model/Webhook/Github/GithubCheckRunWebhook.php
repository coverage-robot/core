<?php

namespace App\Model\Webhook\Github;

use App\Enum\JobState;
use App\Enum\WebhookProcessorEvent;
use InvalidArgumentException;
use Packages\Models\Enum\Provider;
use Symfony\Component\HttpFoundation\Request;

class GithubCheckRunWebhook extends AbstractGithubWebhook implements PipelineStateChangeWebhookInterface
{
    public function __construct(
        Provider $provider,
        string $owner,
        string $repository,
        private readonly string $checkRunId,
        private readonly JobState $jobState,
        private readonly string $commit,
        private readonly ?string $pullRequest,
    ) {
        parent::__construct($provider, $owner, $repository);
    }

    public function getExternalId(): string
    {
        return $this->checkRunId;
    }

    public function getJobState(): JobState
    {
        return $this->jobState;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getPullRequest(): ?string
    {
        return $this->pullRequest;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromRequest(Provider $provider, Request $request): self
    {
        $body = $request->toArray();

        if (
            !isset($body['action']) ||
            !isset($body['repository']['owner']) ||
            !isset($body['repository']['name']) ||
            !isset($body['check_run']['id']) ||
            !isset($body['check_run']['head_sha']) ||
            !isset($body['check_run']['conclusion'])
        ) {
            throw new InvalidArgumentException('Provided request is not a valid Github check run webhook');
        }

        return new self(
            $provider,
            (string)((array)$body['repository']['owner'])['login'],
            (string)$body['repository']['name'],
            (string)$body['check_run']['id'],
            JobState::from((string)$body['check_run']['conclusion']),
            (string)$body['check_run']['head_sha'],
            isset($body['check_run']['pull_requests'][0]['number']) ?
                (string)$body['check_run']['pull_requests'][0]['number'] :
                null,
        );
    }

    public function __toString(): string
    {
        return sprintf(
            '%s#%s-%s-%s-%s-%s',
            get_class($this),
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
