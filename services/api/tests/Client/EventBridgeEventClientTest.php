<?php

namespace App\Tests\Client;

use App\Client\EventBridgeEventClient;
use App\Enum\EnvironmentVariable;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\Result\PutEventsResponse;
use AsyncAws\EventBridge\ValueObject\PutEventsRequestEntry;
use DateTimeImmutable;
use DateTimeInterface;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventSource;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Enum\JobState;
use Packages\Event\Model\JobStateChange;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class EventBridgeEventClientTest extends KernelTestCase
{
    #[DataProvider('failedEntryCountDataProvider')]
    public function testPublishEvent(int $failedEntryCount, bool $expectSuccess): void
    {
        $eventTime = new DateTimeImmutable();
        $event = [
            'type' => Event::JOB_STATE_CHANGE->value,
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'ref' => 'mock-ref',
            'commit' => 'mock-commit',
            'parent' => ['mock-parent-1'],
            'pullRequest' => null,
            'baseCommit' => null,
            'baseRef' => null,
            'externalId' => 'mock-id',
            'state' => JobState::COMPLETED->value,
            'eventTime' => $eventTime->format(DateTimeInterface::ATOM),
        ];

        $jobStateChange = new JobStateChange(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            parent: ['mock-parent-1'],
            externalId: 'mock-id',
            state: JobState::COMPLETED,
            eventTime: $eventTime
        );

        $mockResult = ResultMockFactory::create(
            PutEventsResponse::class,
            [
                'FailedEntryCount' => $failedEntryCount,
            ]
        );

        $mockEventBridgeClient = $this->createMock(EventBridgeClient::class);
        $mockEventBridgeClient->expects($this->once())
            ->method('putEvents')
            ->with(
                new PutEventsRequest([
                    'Entries' => [
                        new PutEventsRequestEntry([
                            'EventBusName' => 'mock-event-bus',
                            'Source' => EventSource::API->value,
                            'DetailType' => Event::JOB_STATE_CHANGE->value,
                            'Detail' => json_encode($event, JSON_THROW_ON_ERROR),
                            'TraceHeader' => 'mock-trace-id'
                        ])
                    ],
                ])
            )
            ->willReturn($mockResult);


        $eventBridgeEventService = new EventBridgeEventClient(
            $mockEventBridgeClient,
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::TRACE_ID->value => 'mock-trace-id',
                    EnvironmentVariable::EVENT_BUS->value => 'mock-event-bus'
                ]
            ),
            $this->getContainer()->get(SerializerInterface::class)
        );

        $success = $eventBridgeEventService->publishEvent($jobStateChange);

        $this->assertEquals($expectSuccess, $success);
    }

    public static function failedEntryCountDataProvider(): array
    {
        return [
            'No failures' => [
                0,
                true
            ],
            'One failure' => [
                1,
                false
            ],
        ];
    }
}
