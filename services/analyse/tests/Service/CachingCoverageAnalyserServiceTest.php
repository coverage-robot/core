<?php

namespace App\Tests\Service;

use App\Model\ReportWaypoint;
use App\Query\Result\QueryResultInterface;
use App\Query\Result\TotalCoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\CachingCoverageAnalyserService;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\DiffParserServiceInterface;
use App\Service\History\CommitHistoryServiceInterface;
use App\Service\QueryServiceInterface;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CachingCoverageAnalyserServiceTest extends TestCase
{
    public function testCachingAndLazyLoadingReportMetrics(): void
    {
        $mockQueryService = $this->createMock(QueryServiceInterface::class);

        // We're only performing 2 queries, meaning the others must be lazy
        // loaded
        $mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnCallback(
                static fn(string $queryClass): QueryResultInterface|null => match ($queryClass) {
                    TotalUploadsQuery::class => new TotalUploadsQueryResult(
                        ['1'],
                        [new DateTimeImmutable('2021-01-01')],
                        [new Tag('mock-tag', 'mock-commit')],
                        null
                    ),
                    TotalCoverageQuery::class => new TotalCoverageQueryResult(
                        95.6,
                        2,
                        4,
                        1,
                        1
                    ),
                    default => null,
                }
            );

        $mockDiffParserService = $this->createMock(DiffParserServiceInterface::class);
        $mockCommitHistoryService = $this->createMock(CommitHistoryServiceInterface::class);
        $mockCarryforwardTagService = $this->createMock(CarryforwardTagServiceInterface::class);

        $cachingCoverageAnalyserService = new CachingCoverageAnalyserService(
            $mockQueryService,
            $mockDiffParserService,
            $mockCommitHistoryService,
            $mockCarryforwardTagService
        );

        $mockWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'owner',
            repository: 'repository',
            ref: 'ref',
            commit: 'commit',
            history: [],
            diff: [],
            pullRequest: 12
        );

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

    #[DataProvider('diffCoverageDataProvider')]
    public function testCachingDiffCoveragePercentage(
        array $diff,
        TotalCoverageQueryResult $diffCoverageQueryResult,
        ?float $expectedDiffCoveragePercentage
    ): void {
        $mockQueryService = $this->createMock(QueryServiceInterface::class);

        // We're only performing 2 queries, meaning the others must be lazy
        // loaded
        $mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnCallback(
                static fn(string $queryClass): QueryResultInterface|null => match ($queryClass) {
                    TotalUploadsQuery::class => new TotalUploadsQueryResult(
                        ['1'],
                        [new DateTimeImmutable('2021-01-01')],
                        [new Tag('mock-tag', 'mock-commit')],
                        null
                    ),
                    TotalCoverageQuery::class => $diffCoverageQueryResult,
                    default => null,
                }
            );

        $mockWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'owner',
            repository: 'repository',
            ref: 'ref',
            commit: 'commit',
            history: [],
            diff: [],
            pullRequest: 12
        );

        $mockDiffParserService = $this->createMock(DiffParserServiceInterface::class);
        $mockDiffParserService->expects($this->once())
            ->method('get')
            ->with($mockWaypoint)
            ->willReturn($diff);

        $mockCarryforwardTagService = $this->createMock(CarryforwardTagServiceInterface::class);

        $mockCommitHistoryService = $this->createMock(CommitHistoryServiceInterface::class);

        $cachingCoverageAnalyserService = new CachingCoverageAnalyserService(
            $mockQueryService,
            $mockDiffParserService,
            $mockCommitHistoryService,
            $mockCarryforwardTagService
        );

        $report = $cachingCoverageAnalyserService->analyse($mockWaypoint);

        $this->assertSame(
            $expectedDiffCoveragePercentage,
            $report->getDiffCoveragePercentage()
        );

        // We never perform another query on the same metric a second time
        $mockQueryService->expects($this->never())
            ->method('runQuery');

        $this->assertSame(
            $expectedDiffCoveragePercentage,
            $report->getDiffCoveragePercentage()
        );
    }

    public static function diffCoverageDataProvider(): array
    {
        return [
            'No diff coverage' => [
                [
                    'mock-file' => [1,2,3]
                ],
                new TotalCoverageQueryResult(
                    0.0,
                    3,
                    0,
                    0,
                    0
                ),
                0
            ],
            'Partial diff coverage' => [
                [
                    'mock-file' => [1,2,3]
                ],
                new TotalCoverageQueryResult(
                    66.6,
                    3,
                    2,
                    0,
                    0
                ),
                66.6
            ],
            'Full diff coverage' => [
                [
                    'mock-file' => [1,2,3]
                ],
                new TotalCoverageQueryResult(
                    100,
                    3,
                    3,
                    0,
                    0
                ),
                100
            ],
            'No coverable lines' => [
                [
                    'mock-file' => [1,2,3]
                ],
                new TotalCoverageQueryResult(
                    0,
                    0,
                    0,
                    0,
                    0
                ),
                null
            ],
        ];
    }
}
