<?php

namespace App\Model\Webhook\Github;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookState;
use Packages\Models\Enum\Provider;

class GithubCheckRunWebhook extends GithubWebhook
{
    public function __construct(
        Provider $provider,
        WebhookState $type,
        string $owner,
        string $repository,
        private readonly string $commit,
        private readonly ?string $pullRequest
    ) {
        parent::__construct($provider, $type, $owner, $repository);
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getPullRequest(): string
    {
        return $this->pullRequest;
    }

    public static function fromBody(Provider $provider, array $body): self
    {
        return new self(
            $provider,
            WebhookState::from(strtolower((string)$body['action'])),
            (string)$body['repository']['owner']['login'],
            (string)$body['repository']['name'],
            (string)$body['check_run']['head_sha'],
            isset($body['check_run']['pull_requests'][0]) ?
                $body['check_run']['pull_requests'][0]['number'] :
                null
        );
    }

    public static function from(array $data): self
    {
        return new self(
            Provider::from((string)$data['provider']),
            WebhookState::from((string)$data['state']),
            (string)$data['owner'],
            (string)$data['repository'],
            (string)$data['commit'],
            (string)$data['pullRequest']
        );
    }

    public function __toString(): string
    {
        return sprintf(
            '%s#%s-%s-%s-%s-%s-%s',
            get_class($this),
            $this->getProvider()->value,
            $this->getState()->value,
            $this->getOwner(),
            $this->getRepository(),
            $this->getCommit(),
            $this->getPullRequest()
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'provider' => $this->getProvider()->value,
            'state' => $this->getState()->value,
            'owner' => $this->getOwner(),
            'repository' => $this->getRepository(),
            'commit' => $this->getCommit(),
            'pullRequest' => $this->getPullRequest()
        ];
    }

    public function getEvent(): WebhookProcessorEvent
    {
        return WebhookProcessorEvent::PIPELINE_STATE_CHANGE;
    }
}
