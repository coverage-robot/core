<?php

namespace App\Tests\Service;

use App\Model\ReportWaypoint;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\CachingCoverageAnalyserService;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\Diff\DiffParserService;
use App\Service\QueryService;
use Packages\Contracts\Provider\Provider;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\TestCase;

class CachingCoverageAnalyserServiceTest extends TestCase
{
    public function testCachingAndLazyLoadingReportMetrics(): void
    {
        $mockQueryService = $this->createMock(QueryService::class);

        // We're only performing 2 queries, meaning the others must be lazy
        // loaded
        $mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnCallback(
                static fn(string $queryClass) => match ($queryClass) {
                    TotalUploadsQuery::class => new TotalUploadsQueryResult(
                        ['1'],
                        [new Tag('mock-tag', 'mock-commit')],
                        null
                    ),
                    TotalCoverageQuery::class => new CoverageQueryResult(
                        95.6,
                        2,
                        4,
                        1,
                        1
                    ),
                    default => null,
                }
            );

        $mockDiffParserService = $this->createMock(DiffParserService::class);
        $mockCarryforwardTagService = $this->createMock(CarryforwardTagService::class);

        $cachingCoverageAnalyserService = new CachingCoverageAnalyserService(
            $mockQueryService,
            $mockDiffParserService,
            $mockCarryforwardTagService
        );

        $mockWaypoint = $this->createMock(ReportWaypoint::class);
        $mockWaypoint->method('getProvider')
            ->willReturn(Provider::GITHUB);

        $report = $cachingCoverageAnalyserService->analyse($mockWaypoint);

        $this->assertEquals(
            95.6,
            $report->getCoveragePercentage()
        );

        // We never perform another query on the same metric a second time
        $mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertEquals(
            95.6,
            $report->getCoveragePercentage()
        );
    }
}