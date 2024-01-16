<?php

namespace App\Model\Webhook;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookType;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Model\Webhook\Github\GithubPushWebhook;
use Packages\Contracts\Provider\Provider;
use Stringable;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Validator\Constraints as Assert;

#[DiscriminatorMap(
    'type',
    [
        WebhookType::GITHUB_CHECK_RUN->value => GithubCheckRunWebhook::class,
        WebhookType::GITHUB_PUSH->value => GithubPushWebhook::class,
    ]
)]
interface WebhookInterface extends Stringable
{
    public function getProvider(): Provider;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[\\w\-\.]+$/i')]
    public function getOwner(): string;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[\\w\-\.]+$/i')]
    public function getRepository(): string;

    public function getType(): WebhookType;

    public function getEvent(): WebhookProcessorEvent;

    #[Assert\NotBlank]
    public function getMessageGroup(): string;
}
