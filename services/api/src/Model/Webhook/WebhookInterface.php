<?php

declare(strict_types=1);

namespace App\Model\Webhook;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookType;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Model\Webhook\Github\GithubPushWebhook;
use DateTimeImmutable;
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

    /**
     * **Note:** Its important that the validation constraint checks the event occurred less than
     * 10 seconds in the future.
     *
     * Generally speaking, the event time should in the past (this before "now"), but GitHub appears to
     * occasionally send webhooks with an event time ahead of the time we process the webhook (presumably
     * due to clock skew).
     *
     * @see WebhookValidationServiceTest
     */
    #[Assert\LessThanOrEqual('+10 seconds')]
    public function getEventTime(): DateTimeImmutable;

    public function getType(): WebhookType;

    public function getEvent(): WebhookProcessorEvent;

    #[Assert\NotBlank]
    public function getMessageGroup(): string;
}
