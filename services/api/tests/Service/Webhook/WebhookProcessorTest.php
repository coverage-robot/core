<?php

namespace App\Tests\Service\Webhook;

use App\Entity\Project;
use App\Enum\WebhookProcessorEvent;
use App\Enum\WebhookType;
use App\Model\Webhook\WebhookInterface;
use App\Service\Webhook\JobStateChangeWebhookProcessor;
use App\Service\Webhook\WebhookProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class WebhookProcessorTest extends KernelTestCase
{
    #[DataProvider('webhookPayloadDataProvider')]
    public function testProcessUsingValidEvent(WebhookType $type, array $payload): void
    {
        $mockProcessor = $this->createMock(JobStateChangeWebhookProcessor::class);
        $mockProcessor->expects($this->once())
            ->method('process');

        $webhookProcessor = new WebhookProcessor(
            [
                WebhookProcessorEvent::JOB_STATE_CHANGE->value => $mockProcessor,
            ]
        );

        $webhookProcessor->process(
            $this->createMock(Project::class),
            $this->getContainer()->get(SerializerInterface::class)
                ->denormalize(
                    array_merge(
                        [
                            'type' => $type->value,
                        ],
                        $payload
                    ),
                    WebhookInterface::class
                ),
            true
        );
    }

    public static function webhookPayloadDataProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../../Fixture/Webhook/*.json') as $payload) {
            yield basename($payload) => [
                WebhookType::GITHUB_CHECK_RUN,
                json_decode(
                    file_get_contents($payload),
                    true
                )
            ];
        }
    }
}
