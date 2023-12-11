<?php

namespace App\Tests\Model;

use App\Model\CachingPublishableCoverageData;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\Diff\DiffParserService;
use App\Service\QueryService;
use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\Upload;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CachingPublishableCoverageDataTest extends TestCase
{
    private CachingPublishableCoverageData|MockObject $cachedPublishableCoverageData;

    private QueryService|MockObject $mockQueryService;

    private DiffParserService|MockObject $mockDiffParserService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockQueryService = $this->createMock(QueryService::class);

        $this->mockDiffParserService = $this->createMock(DiffParserService::class);

        $upload = $this->createMock(Upload::class);
        $upload->method('getProvider')
            ->willReturn(Provider::GITHUB);

        $this->cachedPublishableCoverageData = new CachingPublishableCoverageData(
            $this->mockQueryService,
            $this->mockDiffParserService,
            $this->createMock(CarryforwardTagService::class),
            $upload
        );
    }

    public function testGetUploads(): void
    {
        $result = new TotalUploadsQueryResult(
            ['a'],
            [
                new Tag('c', 'mock-commit')
            ],
            DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000')
        );

        $this->mockQueryService->expects($this->once())
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls($result);

        $this->assertEquals(
            $result,
            $this->cachedPublishableCoverageData->getUploads()
        );

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(
            $result,
            $this->cachedPublishableCoverageData->getUploads()
        );
    }

    public function testGetSuccessfulUploads(): void
    {
        $result = new TotalUploadsQueryResult(
            ['a'],
            [
                new Tag('c', 'mock-commit')
            ],
            DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000')
        );

        $this->mockQueryService->expects($this->once())
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls($result);

        $this->assertEquals(
            ['a'],
            $this->cachedPublishableCoverageData->getSuccessfulUploads()
        );

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(
            ['a'],
            $this->cachedPublishableCoverageData->getSuccessfulUploads()
        );
    }

    public function testGetLatestSuccessfulUpload(): void
    {
        $result = new TotalUploadsQueryResult(
            ['a'],
            [
                new Tag('c', 'mock-commit')
            ],
            DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000')
        );

        $this->mockQueryService->expects($this->once())
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls($result);

        $this->assertEquals(
            DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000'),
            $this->cachedPublishableCoverageData->getLatestSuccessfulUpload()
        );

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(
            DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000'),
            $this->cachedPublishableCoverageData->getLatestSuccessfulUpload()
        );
    }

    public function testGetAtLeastPartiallyCoveredLines(): void
    {
        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new TotalUploadsQueryResult(
                    ['mock-upload'],
                    [
                        new Tag('tag-1', 'mock-commit')
                    ],
                    DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000')
                ),
                new CoverageQueryResult(
                    0,
                    6,
                    1,
                    2,
                    3
                )
            );

        $this->assertEquals(3, $this->cachedPublishableCoverageData->getAtLeastPartiallyCoveredLines());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(3, $this->cachedPublishableCoverageData->getAtLeastPartiallyCoveredLines());
    }

    public function testGetCoveragePercentage(): void
    {
        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new TotalUploadsQueryResult(
                    ['mock-upload'],
                    [
                        new Tag('tag-1', 'mock-commit')
                    ],
                    DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000')
                ),
                new CoverageQueryResult(
                    97.1,
                    6,
                    1,
                    2,
                    3
                )
            );

        $this->assertEquals(97.1, $this->cachedPublishableCoverageData->getCoveragePercentage());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(97.1, $this->cachedPublishableCoverageData->getCoveragePercentage());
    }

    public function testGetUncoveredLines(): void
    {
        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new TotalUploadsQueryResult(
                    ['mock-upload'],
                    [
                        new Tag('tag-1', 'mock-commit')
                    ],
                    DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000')
                ),
                new CoverageQueryResult(
                    97.0,
                    6,
                    1,
                    2,
                    3
                )
            );

        $this->assertEquals(3, $this->cachedPublishableCoverageData->getUncoveredLines());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(3, $this->cachedPublishableCoverageData->getUncoveredLines());
    }

    public function testGetTotalLines(): void
    {
        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new TotalUploadsQueryResult(
                    ['mock-upload'],
                    [
                        new Tag('tag-1', 'mock-commit')
                    ],
                    DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000')
                ),
                new CoverageQueryResult(
                    97.0,
                    6,
                    1,
                    2,
                    3
                )
            );

        $this->assertEquals(6, $this->cachedPublishableCoverageData->getTotalLines());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(6, $this->cachedPublishableCoverageData->getTotalLines());
    }

    public function testGetTagCoverage(): void
    {
        $tags = new TagCoverageCollectionQueryResult(
            [
                new TagCoverageQueryResult(
                    new Tag('custom-tag', 'commit-sha'),
                    97.0,
                    6,
                    1,
                    2,
                    3
                )
            ]
        );

        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new TotalUploadsQueryResult(
                    ['mock-upload'],
                    [
                        new Tag('tag-1', 'mock-commit')
                    ],
                    null
                ),
                $tags
            );

        $this->assertEquals($tags, $this->cachedPublishableCoverageData->getTagCoverage());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals($tags, $this->cachedPublishableCoverageData->getTagCoverage());
    }

    public function testGetDiffCoveragePercentage(): void
    {
        $this->mockDiffParserService->expects($this->once())
            ->method('get')
            ->willReturn(
                [
                    'foo.php' => [1,2,3]
                ]
            );

        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new TotalUploadsQueryResult(
                    ['mock-upload'],
                    [
                        new Tag('tag-1', 'mock-commit')
                    ],
                    null
                ),
                new CoverageQueryResult(
                    97.0,
                    6,
                    1,
                    2,
                    3
                )
            );

        $this->assertEquals(97.0, $this->cachedPublishableCoverageData->getDiffCoveragePercentage());

        $this->mockDiffParserService->expects($this->never())
            ->method('get');
        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(97.0, $this->cachedPublishableCoverageData->getDiffCoveragePercentage());
    }

    public function testGetDiffCoveragePercentageWhenDiffHasNoCoverableLines(): void
    {
        $this->mockDiffParserService->expects($this->once())
            ->method('get')
            ->willReturn(
                [
                    'foo.php' => [1,2,3]
                ]
            );

        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new TotalUploadsQueryResult(
                    ['mock-upload'],
                    [
                        new Tag('tag-1', 'mock-commit')
                    ],
                    null
                ),
                new CoverageQueryResult(
                    0,
                    0,
                    0,
                    0,
                    0
                )
            );

        $this->assertNull($this->cachedPublishableCoverageData->getDiffCoveragePercentage());

        $this->mockDiffParserService->expects($this->never())
            ->method('get');
        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertNull($this->cachedPublishableCoverageData->getDiffCoveragePercentage());
    }

    public function testGetLeastCoveredDiffFiles(): void
    {
        $this->mockDiffParserService->expects($this->once())
            ->method('get')
            ->willReturn(
                [
                    'foo.php' => [1,2,3]
                ]
            );

        $files = new FileCoverageCollectionQueryResult(
            [
                new FileCoverageQueryResult(
                    'foo.php',
                    97.0,
                    6,
                    1,
                    2,
                    3,
                )
            ]
        );

        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new TotalUploadsQueryResult(
                    ['mock-upload'],
                    [
                        new Tag('tag-1', 'mock-commit')
                    ],
                    null
                ),
                $files
            );

        $this->assertEquals($files, $this->cachedPublishableCoverageData->getLeastCoveredDiffFiles(1));

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');
        $this->mockDiffParserService->expects($this->never())
            ->method('get');

        $this->assertEquals($files, $this->cachedPublishableCoverageData->getLeastCoveredDiffFiles(1));
    }

    public function testGetDiffLineCoverage(): void
    {
        $this->mockDiffParserService->expects($this->once())
            ->method('get')
            ->willReturn(
                [
                    'foo.php' => [1,2,3]
                ]
            );

        $lines = new LineCoverageCollectionQueryResult(
            [
                new LineCoverageQueryResult(
                    'foo.php',
                    6,
                    LineState::COVERED,
                    false,
                    false,
                    true,
                    1,
                    1
                )
            ]
        );

        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new TotalUploadsQueryResult(
                    ['mock-upload'],
                    [
                        new Tag('tag-1', 'mock-commit')
                    ],
                    null
                ),
                $lines
            );

        $this->assertEquals($lines, $this->cachedPublishableCoverageData->getDiffLineCoverage());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');
        $this->mockDiffParserService->expects($this->never())
            ->method('get');

        $this->assertEquals($lines, $this->cachedPublishableCoverageData->getDiffLineCoverage());
    }

    public function testDiffMethodsNeverQueryWhenDiffEmpty(): void
    {
        $this->mockDiffParserService->expects($this->exactly(3))
            ->method('get')
            ->willReturn([]);

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(
            0,
            $this->cachedPublishableCoverageData->getDiffCoveragePercentage()
        );
        $this->assertEquals(
            new LineCoverageCollectionQueryResult([]),
            $this->cachedPublishableCoverageData->getDiffLineCoverage()
        );
        $this->assertEquals(
            new FileCoverageCollectionQueryResult([]),
            $this->cachedPublishableCoverageData->getLeastCoveredDiffFiles()
        );
    }
}
