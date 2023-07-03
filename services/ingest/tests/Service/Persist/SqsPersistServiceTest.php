<?php

namespace App\Tests\Service\Persist;

use App\Service\Persist\BigQueryPersistService;
use App\Service\Persist\SqsPersistService;
use DateTimeImmutable;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Stamp\SentStamp;

class SqsPersistServiceTest extends TestCase
{
    #[DataProvider('uploadDataProvider')]
    public function testPersist(Upload $upload, Coverage $coverage): void
    {
        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($upload)
            ->willReturn(Envelope::wrap(
                $upload,
                [new SentStamp('')]
            ));

        $sqsPersistService = new SqsPersistService($messageBus, new NullLogger());

        $successful = $sqsPersistService->persist($upload, $coverage);

        $this->assertTrue($successful);
    }

    public function testGetPriority(): void
    {
        // The SQS message should **always** be persisted after the BigQuery data
        // as been persisted.
        $this->assertTrue(SqsPersistService::getPriority() < BigQueryPersistService::getPriority());
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
