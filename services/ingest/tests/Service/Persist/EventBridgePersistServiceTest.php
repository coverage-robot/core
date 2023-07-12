<?php

namespace App\Tests\Service\Persist;

use App\Service\EventBridgeEventService;
use App\Service\Persist\BigQueryPersistService;
use App\Service\Persist\EventBridgePersistService;
use DateTimeImmutable;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EventBridgePersistServiceTest extends TestCase
{
    #[DataProvider('uploadDataProvider')]
    public function testPersist(Upload $upload, Coverage $coverage): void
    {
        $eventService = $this->createMock(EventBridgeEventService::class);
        $eventService->expects($this->once())
            ->method('publishEvent')
            ->with(CoverageEvent::INGEST_SUCCESS, $upload)
            ->willReturn(true);

        $eventBridgePersistService = new EventBridgePersistService($eventService, new NullLogger());

        $successful = $eventBridgePersistService->persist($upload, $coverage);

        $this->assertTrue($successful);
    }

    public function testGetPriority(): void
    {
        // The SQS message should **always** be persisted after the BigQuery data
        // as been persisted.
        $this->assertTrue(EventBridgePersistService::getPriority() < BigQueryPersistService::getPriority());
    }

    public static function uploadDataProvider(): array
    {
        return [
            [
                new Upload(
                    'mock-uuid-1',
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    ['2'],
                    'mock-branch-reference',
                    1234,
                    'mock-tag',
                    new DateTimeImmutable('2023-05-02T12:00:00+00:00'),
                ),
                new Coverage(CoverageFormat::LCOV, ''),
            ],
            [
                new Upload(
                    'mock-uuid-1',
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '3',
                    ['4'],
                    'mock-branch-reference',
                    1234,
                    'mock-tag',
                    new DateTimeImmutable('2023-05-02T12:00:00+00:00'),
                ),
                new Coverage(CoverageFormat::CLOVER, ''),
            ]
        ];
    }
}
