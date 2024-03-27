<?php

namespace App\Tests\Service;

use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookType;
use App\Model\Webhook\WebhookInterface;
use App\Service\WebhookProcessorService;
use App\Webhook\Processor\WebhookProcessorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class WebhookProcessorTest extends KernelTestCase
{
    #[DataProvider('webhookPayloadDataProvider')]
    public function testProcessUsingValidEvent(
        WebhookType $type,
        WebhookProcessorEvent $event,
        array $payload
    ): void {
        $mockProcessor = $this->createMock(WebhookProcessorInterface::class);
        $mockProcessor->expects($this->once())
            ->method('process');

        $webhookProcessor = new WebhookProcessorService(
            [
                $event->value => $mockProcessor,
            ]
        );

        $webhookProcessor->process(
            $this->getContainer()
                ->get(SerializerInterface::class)
                ->denormalize(
                    ['type' => $type->value, ...$payload],
                    WebhookInterface::class
                )
        );
    }

    public static function webhookPayloadDataProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../Fixture/Webhook/*.json') as $payload) {
            yield basename($payload) => [
                match (basename($payload)) {
                    'github_push.json' => WebhookType::GITHUB_PUSH,
                    default => WebhookType::GITHUB_CHECK_RUN
                },
                match (basename($payload)) {
                    'github_push.json' => WebhookProcessorEvent::COMMITS_PUSHED,
                    default => WebhookProcessorEvent::JOB_STATE_CHANGE
                },
                json_decode(
                    file_get_contents($payload),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
            ];
        }
    }
}
