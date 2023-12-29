<?php

namespace App\Tests\Client;

use App\Client\EventBridgeEventClient;
use App\Enum\EnvironmentVariable;
use App\Tests\Mock\Factory\MockSerializerFactory;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\Result\PutEventsResponse;
use AsyncAws\EventBridge\ValueObject\PutEventsRequestEntry;
use Monolog\Test\TestCase;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventSource;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\CoverageFinalised;
use PHPUnit\Framework\Attributes\DataProvider;

class EventBridgeEventClientTest extends TestCase
{
    #[DataProvider('failedEntryCountDataProvider')]
    public function testPublishEvent(int $failedEntryCount, bool $expectSuccess): void
    {
        $detail = new CoverageFinalised(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            coveragePercentage: 99
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
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::TRACE_ID->value => 'mock-trace-id',
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
