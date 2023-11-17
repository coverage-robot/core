<?php

namespace App\Model\Webhook;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookType;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use Packages\Contracts\Provider\Provider;
use Stringable;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        WebhookType::GITHUB_CHECK_RUN->value => GithubCheckRunWebhook::class,
    ]
)]
interface WebhookInterface extends Stringable
{
    public function getProvider(): Provider;

    public function getOwner(): string;

    public function getRepository(): string;

    public function getType(): WebhookType;

    public function getEvent(): WebhookProcessorEvent;

    public function getMessageGroup(): string;
}
