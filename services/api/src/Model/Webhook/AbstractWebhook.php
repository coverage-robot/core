<?php

namespace App\Model\Webhook;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookState;
use App\Model\Webhook\Github\GithubWebhook;
use InvalidArgumentException;
use Packages\Models\Enum\Provider;

abstract class AbstractWebhook
{
    public function __construct(
        private readonly Provider $provider,
        private readonly WebhookState $type,
        private readonly string $owner,
        private readonly string $repository
    ) {
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getState(): WebhookState
    {
        return $this->type;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromBody(Provider $provider, array $body): AbstractWebhook
    {
        return match ($provider) {
            Provider::GITHUB => GithubWebhook::fromBody($provider, $body)
        };
    }

    abstract public function getEvent(): WebhookProcessorEvent;
}
