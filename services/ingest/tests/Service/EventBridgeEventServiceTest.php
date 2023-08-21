<?php

namespace App\Tests\Service;

use App\Enum\EnvironmentVariable;
use App\Service\EventBridgeEventService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\Result\PutEventsResponse;
use AsyncAws\EventBridge\ValueObject\PutEventsRequestEntry;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\EventBus\CoverageEventSource;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventBridgeEventServiceTest extends TestCase
{
    #[DataProvider('failedEntryCountDataProvider')]
    public function testPublishEvent(int $failedEntryCount, bool $expectSuccess): void
    {
        $upload = new Upload(
            'mock-upload-id',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            ['mock-parent'],
            'mock-ref',
            null,
            new Tag('mock-tag', 'mock-commit')
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
                            'Source' => CoverageEventSource::INGEST->value,
                            'DetailType' => CoverageEvent::INGEST_SUCCESS->value,
                            'Detail' => json_encode($upload, JSON_THROW_ON_ERROR),
                        ])
                    ],
                ])
            )
            ->willReturn($mockResult);


        $eventBridgeEventService = new EventBridgeEventService(
            $mockEventBridgeClient,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::EVENT_BUS->value => 'mock-event-bus'
                ]
            )
        );

        $success = $eventBridgeEventService->publishEvent(
            CoverageEvent::INGEST_SUCCESS,
            $upload
        );

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
