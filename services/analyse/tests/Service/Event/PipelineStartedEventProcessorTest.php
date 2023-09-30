<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Service\Event\PipelineStartedEventProcessor;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use DateTimeInterface;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Enum\PublishableCheckRunStatus;
use Packages\Models\Model\Event\PipelineStarted;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class PipelineStartedEventProcessorTest extends KernelTestCase
{
    public function testProcess(): void
    {
        $validUntil = new DateTimeImmutable('2021-01-01T00:00:00+00:00');

        $event = new PipelineStarted(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            null,
            $validUntil
        );

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->once())
            ->method('queuePublishableMessage')
            ->with(
                self::callback(
                    function (PublishableCheckRunMessage $message) use ($event, $validUntil) {
                        $this->assertEquals(
                            PublishableCheckRunStatus::IN_PROGRESS,
                            $message->getStatus()
                        );
                        return true;
                    }
                )
            )
            ->willReturn(true);

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);

        $pipelineStartedEventProcessor = new PipelineStartedEventProcessor(
            new NullLogger(),
            $this->getContainer()->get(SerializerInterface::class),
            $mockSqsMessageClient,
            $mockEventBridgeEventClient
        );

        $pipelineStartedEventProcessor->process(
            new EventBridgeEvent([
                'detail-type' => CoverageEvent::PIPELINE_STARTED->value,
                'detail' => [
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'ref' => 'mock-ref',
                    'commit' => 'mock-commit',
                    'pullRequest' => null,
                    'startedAt' => $validUntil->format(DateTimeInterface::ATOM),
                    'validUntil' => $validUntil->format(DateTimeInterface::ATOM)
                ]
            ])
        );
    }
}
