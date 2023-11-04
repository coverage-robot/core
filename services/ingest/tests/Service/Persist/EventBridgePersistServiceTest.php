<?php

namespace App\Tests\Service\Persist;

use App\Client\EventBridgeEventClient;
use App\Service\Persist\BigQueryPersistService;
use App\Service\Persist\EventBridgePersistService;
use App\Service\Persist\GcsPersistService;
use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Coverage;
use Packages\Event\Model\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EventBridgePersistServiceTest extends TestCase
{
    #[DataProvider('uploadDataProvider')]
    public function testPersist(Upload $upload, Coverage $coverage): void
    {
        $eventService = $this->createMock(EventBridgeEventClient::class);
        $eventService->expects($this->once())
            ->method('publishEvent')
            ->with(Event::INGEST_SUCCESS, $upload)
            ->willReturn(true);

        $eventBridgePersistService = new EventBridgePersistService($eventService, new NullLogger());

        $successful = $eventBridgePersistService->persist($upload, $coverage);

        $this->assertTrue($successful);
    }

    public function testGetPriority(): void
    {
        // The SQS message should **always** be persisted after the BigQuery data
        // as been loaded into the table.
        $this->assertTrue(EventBridgePersistService::getPriority() < GcsPersistService::getPriority());
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
                    'mock-project-root',
                    1234,
                    new Tag('mock-tag', ''),
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
                    'mock-project-root',
                    1234,
                    new Tag('mock-tag', ''),
                    new DateTimeImmutable('2023-05-02T12:00:00+00:00'),
                ),
                new Coverage(CoverageFormat::CLOVER, ''),
            ]
        ];
    }
}
