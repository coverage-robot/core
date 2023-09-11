<?php

namespace App\Model\Webhook;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookState;
use App\Model\Webhook\Github\AbstractGithubWebhook;
use InvalidArgumentException;
use JsonSerializable;
use Packages\Models\Enum\Provider;
use Stringable;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractWebhook implements JsonSerializable, Stringable
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
    public static function fromRequest(Provider $provider, Request $request): AbstractWebhook
    {
        return match ($provider) {
            Provider::GITHUB => AbstractGithubWebhook::fromRequest($provider, $request)
        };
    }

    abstract public function getEvent(): WebhookProcessorEvent;
}
