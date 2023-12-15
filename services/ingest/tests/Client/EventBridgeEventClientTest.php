<?php

namespace App\Tests\Client;

use App\Client\EventBridgeEventClient;
use App\Enum\EnvironmentVariable;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use App\Tests\Mock\Factory\MockSerializerFactory;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\Result\PutEventsResponse;
use AsyncAws\EventBridge\ValueObject\PutEventsRequestEntry;
use DateTimeImmutable;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventSource;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventBridgeEventClientTest extends TestCase
{
    #[DataProvider('failedEntryCountDataProvider')]
    public function testPublishEvent(int $failedEntryCount, bool $expectSuccess): void
    {
        $ingestSuccessEvent = new IngestSuccess(
            new Upload(
                'mock-upload-id',
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-commit',
                ['mock-parent'],
                'mock-ref',
                'mock-project-root',
                null,
                null,
                null,
                new Tag('mock-tag', 'mock-commit')
            ),
            new DateTimeImmutable()
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
                            'Source' => EventSource::INGEST->value,
                            'DetailType' => Event::INGEST_SUCCESS->value,
                            'Detail' => 'mock-serialized-ingest-event',
                            'TraceHeader' => 'mock-trace-id'
                        ])
                    ],
                ])
            )
            ->willReturn($mockResult);


        $eventBridgeEventService = new EventBridgeEventClient(
            $mockEventBridgeClient,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::EVENT_BUS->value => 'mock-event-bus'
                ]
            ),
            MockSerializerFactory::getMock(
                $this,
                serializeMap: [
                    [
                        $ingestSuccessEvent,
                        'json',
                        [],
                        'mock-serialized-ingest-event'
                    ]
                ]
            )
        );

        $success = $eventBridgeEventService->publishEvent($ingestSuccessEvent);

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
