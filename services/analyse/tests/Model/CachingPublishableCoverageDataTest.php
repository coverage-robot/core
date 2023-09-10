<?php

namespace App\Tests\Model;

use App\Model\CachingPublishableCoverageData;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\Diff\DiffParserService;
use App\Service\QueryService;
use DateTimeImmutable;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CachingPublishableCoverageDataTest extends TestCase
{
    private CachingPublishableCoverageData|MockObject $cachedPublishableCoverageData;

    private QueryService|MockObject $mockQueryService;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockQueryService = $this->createMock(QueryService::class);

        $upload = $this->createMock(Upload::class);
        $upload->method('getProvider')
            ->willReturn(Provider::GITHUB);

        $this->cachedPublishableCoverageData = new CachingPublishableCoverageData(
            $this->mockQueryService,
            $this->createMock(DiffParserService::class),
            $this->createMock(CarryforwardTagService::class),
            $upload
        );
    }

    public function testGetUploads(): void
    {
        $result = TotalUploadsQueryResult::from(
            ['a'],
            ['b'],
            '2023-09-09T01:22:00+0000'
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
        $result = TotalUploadsQueryResult::from(
            ['a'],
            ['b'],
            '2023-09-09T01:22:00+0000'
        );

        $this->mockQueryService->expects($this->once(2))
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

    public function testGetPendingUploads(): void
    {
        $result = TotalUploadsQueryResult::from(
            ['a'],
            ['b'],
            '2023-09-09T01:22:00+0000'
        );

        $this->mockQueryService->expects($this->once(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls($result);

        $this->assertEquals(
            ['b'],
            $this->cachedPublishableCoverageData->getPendingUploads()
        );

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(
            ['b'],
            $this->cachedPublishableCoverageData->getPendingUploads()
        );
    }

    public function testGetLatestSuccessfulUpload(): void
    {
        $result = TotalUploadsQueryResult::from(
            ['a'],
            ['b'],
            '2023-09-09T01:22:00+0000'
        );

        $this->mockQueryService->expects($this->once(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls($result);

        $this->assertEquals(
            DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000'),
            $this->cachedPublishableCoverageData->getLatestSuccessfulUpload()
        );

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(
            DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, '2023-09-09T01:22:00+0000'),
            $this->cachedPublishableCoverageData->getLatestSuccessfulUpload()
        );
    }

    public function testGetAtLeastPartiallyCoveredLines(): void
    {
        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                TotalUploadsQueryResult::from(['mock-upload'], []),
                CoverageQueryResult::from([
                    'lines' => 6,
                    'covered' => 1,
                    'partial' => 2,
                    'uncovered' => 3,
                    'coveragePercentage' => 0.0
                ])
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
                TotalUploadsQueryResult::from(['mock-upload'], []),
                CoverageQueryResult::from([
                    'lines' => 6,
                    'covered' => 1,
                    'partial' => 2,
                    'uncovered' => 3,
                    'coveragePercentage' => 97.1
                ])
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
                TotalUploadsQueryResult::from(['mock-upload'], []),
                CoverageQueryResult::from([
                    'lines' => 6,
                    'covered' => 1,
                    'partial' => 2,
                    'uncovered' => 3,
                    'coveragePercentage' => 97.0
                ])
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
                TotalUploadsQueryResult::from(['mock-upload'], []),
                CoverageQueryResult::from([
                    'lines' => 6,
                    'covered' => 1,
                    'partial' => 2,
                    'uncovered' => 3,
                    'coveragePercentage' => 97.0
                ])
            );

        $this->assertEquals(6, $this->cachedPublishableCoverageData->getTotalLines());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(6, $this->cachedPublishableCoverageData->getTotalLines());
    }

    public function testGetTagCoverage(): void
    {
        $files = TagCoverageCollectionQueryResult::from([
            [
                'tag' => 'custom-tag',
                'commit' => 'commit-sha',
                'lines' => 6,
                'covered' => 1,
                'partial' => 2,
                'uncovered' => 3,
                'coveragePercentage' => 97.0
            ]
        ]);

        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                TotalUploadsQueryResult::from(['mock-upload'], []),
                $files
            );

        $this->assertEquals($files, $this->cachedPublishableCoverageData->getTagCoverage());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals($files, $this->cachedPublishableCoverageData->getTagCoverage());
    }

    public function testGetDiffCoveragePercentage(): void
    {
        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                TotalUploadsQueryResult::from(['mock-upload'], []),
                CoverageQueryResult::from([
                    'lines' => 6,
                    'covered' => 1,
                    'partial' => 2,
                    'uncovered' => 3,
                    'coveragePercentage' => 97.0
                ])
            );

        $this->assertEquals(97.0, $this->cachedPublishableCoverageData->getDiffCoveragePercentage());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(97.0, $this->cachedPublishableCoverageData->getDiffCoveragePercentage());
    }

    public function testGetLeastCoveredDiffFiles(): void
    {
        $files = FileCoverageCollectionQueryResult::from([
            [
                'fileName' => 'foo.php',
                'lines' => 6,
                'covered' => 1,
                'partial' => 2,
                'uncovered' => 3,
                'coveragePercentage' => 97.0
            ]
        ]);

        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                TotalUploadsQueryResult::from(['mock-upload'], []),
                $files
            );

        $this->assertEquals($files, $this->cachedPublishableCoverageData->getLeastCoveredDiffFiles(1));

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals($files, $this->cachedPublishableCoverageData->getLeastCoveredDiffFiles(1));
    }

    public function testGetDiffLineCoverage(): void
    {
        $lines = LineCoverageCollectionQueryResult::from([
            [
                'fileName' => 'foo.php',
                'lineNumber' => 6,
                'state' => LineState::COVERED->value,
            ]
        ]);

        $this->mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                TotalUploadsQueryResult::from(['mock-upload'], []),
                $lines
            );

        $this->assertEquals($lines, $this->cachedPublishableCoverageData->getDiffLineCoverage());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals($lines, $this->cachedPublishableCoverageData->getDiffLineCoverage());
    }
}
