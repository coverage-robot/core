<?php

namespace App\Tests\Service;

use App\Model\ReportInterface;
use App\Model\ReportWaypoint;
use App\Query\FileCoverageQuery;
use App\Query\LineCoverageQuery;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\CachingCoverageAnalyserService;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\Diff\DiffParserService;
use App\Service\QueryService;
use Packages\Contracts\Provider\Provider;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CachingCoverageAnalyserServiceTest extends TestCase
{
    public function testAnalysingIsCachedForSameEvent(): void
    {
        $mockQueryService = $this->getMockedQueryService();

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

        $this->assertInstanceOf(
            ReportInterface::class,
            $report
        );

        $mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertSame(
            $report,
            $cachingCoverageAnalyserService->analyse($mockWaypoint)
        );
    }

    public function testAnalysingIsNotCachedForDifferentEvent(): void
    {
        $mockQueryService = $this->getMockedQueryService();

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

        $this->assertInstanceOf(
            ReportInterface::class,
            $report
        );

        $mockWaypoint = $this->createMock(ReportWaypoint::class);
        $mockWaypoint->method('getProvider')
            ->willReturn(Provider::GITHUB);

        $this->assertNotSame(
            $report,
            $cachingCoverageAnalyserService->analyse($mockWaypoint)
        );
    }

    private function getMockedQueryService(): MockObject|QueryService
    {
        $mockQueryService = $this->createMock(QueryService::class);

        $mockQueryService->expects($this->atLeastOnce())
            ->method('runQuery')
            ->willReturnCallback(
                static fn(string $queryClass) => match ($queryClass) {
                    TotalUploadsQuery::class => new TotalUploadsQueryResult(
                        ['1'],
                        [new Tag('mock-tag', 'mock-commit')],
                        null
                    ),
                    TotalCoverageQuery::class => new CoverageQueryResult(
                        100,
                        1,
                        1,
                        0,
                        0
                    ),
                    TotalTagCoverageQuery::class => new TagCoverageCollectionQueryResult(
                        [
                            new TagCoverageQueryResult(
                                new Tag('mock-tag', 'mock-commit'),
                                100,
                                1,
                                0,
                                0,
                                0
                            )
                        ]
                    ),
                    FileCoverageQuery::class => new FileCoverageCollectionQueryResult(
                        [
                            new FileCoverageQueryResult(
                                'mock-file',
                                100,
                                1,
                                1,
                                0,
                                0
                            )
                        ]
                    ),
                    LineCoverageQuery::class => new LineCoverageCollectionQueryResult([
                        new LineCoverageQueryResult(
                            'mock-file',
                            1,
                            LineState::COVERED,
                            false,
                            false,
                            true,
                            0,
                            0
                        )
                    ]),
                    default => null,
                }
            );

        return $mockQueryService;
    }
}
