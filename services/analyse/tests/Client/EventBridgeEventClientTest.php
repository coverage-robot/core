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
use Monolog\Test\TestCase;
use Packages\Event\Enum\Event;
use Packages\Event\Enum\EventSource;
use Packages\Event\Model\CoverageFinalised;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\Attributes\DataProvider;

class EventBridgeEventClientTest extends TestCase
{
    #[DataProvider('failedEntryCountDataProvider')]
    public function testPublishEvent(int $failedEntryCount, bool $expectSuccess): void
    {
        $detail = new CoverageFinalised(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            null,
            99,
            new DateTimeImmutable()
        );

        $mockResult = ResultMockFactory::create(PutEventsResponse::class, [
            'FailedEntryCount' => $failedEntryCount,
        ]);

        $mockEventBridgeClient = $this->createMock(EventBridgeClient::class);
        $mockEventBridgeClient->expects($this->once())
            ->method('putEvents')
            ->with(
                new PutEventsRequest([
                    'Entries' => [
                        new PutEventsRequestEntry([
                            'EventBusName' => 'mock-event-bus',
                            'Source' => EventSource::ANALYSE->value,
                            'DetailType' => Event::COVERAGE_FINALISED->value,
                            'Detail' => 'mock-serialized-json',
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
                    EnvironmentVariable::EVENT_BUS->value => 'mock-event-bus',
                ]
            ),
            MockSerializerFactory::getMock(
                $this,
                [
                    [
                        $detail,
                        'json',
                        [],
                        'mock-serialized-json'
                    ]
                ]
            )
        );

        $success = $eventBridgeEventService->publishEvent($detail);

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
