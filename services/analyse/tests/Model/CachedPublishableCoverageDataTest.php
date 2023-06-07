<?php

namespace App\Tests\Model;

use App\Model\CachedPublishableCoverageData;
use App\Model\QueryResult\IntegerQueryResult;
use App\Model\QueryResult\TotalCoverageQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\QueryService;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CachedPublishableCoverageDataTest extends TestCase
{
    private CachedPublishableCoverageData|MockObject $cachedPublishableCoverageData;

    private QueryService|MockObject $mockQueryService;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockQueryService = $this->createMock(QueryService::class);

        $this->cachedPublishableCoverageData = new CachedPublishableCoverageData(
            $this->mockQueryService,
            $this->createMock(Upload::class)
        );
    }

    public function testGetAtLeastPartiallyCoveredLines()
    {
        $this->mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalCoverageQuery::class)
            ->willReturn(
                TotalCoverageQueryResult::from([
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

    public function testGetCoveragePercentage()
    {
        $this->mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalCoverageQuery::class)
            ->willReturn(TotalCoverageQueryResult::from([
                'lines' => 6,
                'covered' => 1,
                'partial' => 2,
                'uncovered' => 3,
                'coveragePercentage' => 97.1
            ]));

        $this->assertEquals(97.1, $this->cachedPublishableCoverageData->getCoveragePercentage());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(97.1, $this->cachedPublishableCoverageData->getCoveragePercentage());
    }

    public function testGetUncoveredLines()
    {
        $this->mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalCoverageQuery::class)
            ->willReturn(TotalCoverageQueryResult::from([
                'lines' => 6,
                'covered' => 1,
                'partial' => 2,
                'uncovered' => 3,
                'coveragePercentage' => 97.0
            ]));

        $this->assertEquals(3, $this->cachedPublishableCoverageData->getUncoveredLines());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(3, $this->cachedPublishableCoverageData->getUncoveredLines());
    }

    public function testGetTotalLines()
    {
        $this->mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalCoverageQuery::class)
            ->willReturn(TotalCoverageQueryResult::from([
                'lines' => 6,
                'covered' => 1,
                'partial' => 2,
                'uncovered' => 3,
                'coveragePercentage' => 97.0
            ]));

        $this->assertEquals(6, $this->cachedPublishableCoverageData->getTotalLines());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(6, $this->cachedPublishableCoverageData->getTotalLines());
    }

    public function testGetTotalUploads()
    {
        $this->mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalUploadsQuery::class)
            ->willReturn(IntegerQueryResult::from(2));

        $this->assertEquals(2, $this->cachedPublishableCoverageData->getTotalUploads());

        $this->mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(2, $this->cachedPublishableCoverageData->getTotalUploads());
    }
}
