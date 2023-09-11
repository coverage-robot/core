<?php

namespace App\Tests\Service\Webhook;

use App\Enum\WebhookProcessorEvent;
use App\Model\Webhook\AbstractWebhook;
use App\Model\Webhook\Github\AbstractGithubWebhook;
use App\Service\Webhook\PipelineStateChangeWebhookProcessor;
use App\Service\Webhook\WebhookProcessor;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class WebhookProcessorTest extends TestCase
{
    #[DataProvider('webhookPayloadDataProvider')]
    public function testProcessUsingValidEvent(Provider $provider, string $event, string $payload): void
    {
        $mockProcessor = $this->createMock(PipelineStateChangeWebhookProcessor::class);
        $mockProcessor->expects($this->once())
            ->method('process');

        $webhookProcessor = new WebhookProcessor(
            [
                WebhookProcessorEvent::PIPELINE_STATE_CHANGE->value => $mockProcessor,
            ]
        );

        $webhookProcessor->process(
            AbstractWebhook::fromRequest(
                $provider,
                new Request(
                    server: ['HTTP_' . AbstractGithubWebhook::GITHUB_EVENT_HEADER => $event],
                    content: $payload
                )
            )
        );
    }

    public static function webhookPayloadDataProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../../Fixture/Webhook/*.json') as $payload) {
            yield basename($payload) => [Provider::GITHUB, 'check_run', file_get_contents($payload)];
        }
    }
}
