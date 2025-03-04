<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Model\CoverageReportInterface;
use App\Model\ReportWaypoint;
use App\Query\FileCoverageQuery;
use App\Query\LineCoverageQuery;
use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\LineCoverageQueryResult;
use App\Query\Result\QueryResultInterface;
use App\Query\Result\QueryResultIterator;
use App\Query\Result\TagCoverageQueryResult;
use App\Query\Result\TotalCoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\CachingCoverageAnalyserService;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\DiffParserServiceInterface;
use App\Service\History\CommitHistoryServiceInterface;
use App\Service\QueryService;
use App\Service\QueryServiceInterface;
use ArrayIterator;
use DateTimeImmutable;
use Packages\Contracts\Line\LineState;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CachingCoverageAnalyserServiceTest extends TestCase
{
    public function testAnalysingWaypoint(): void
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
            pullRequest: 12
        );

        $mockDiffParserService = $this->createMock(DiffParserServiceInterface::class);
        $mockDiffParserService->expects($this->exactly(4))
            ->method('get')
            ->with($waypoint)
            ->willReturn([
                'mock-file' => [1, 2, 3]
            ]);

        $mockQueryService = $this->getMockedQueryService();

        $mockCommitHistoryService = $this->createMock(CommitHistoryServiceInterface::class);

        $mockCarryforwardTagService = $this->createMock(CarryforwardTagServiceInterface::class);

        $coverageAnalyserService = new CachingCoverageAnalyserService(
            $mockQueryService,
            $mockDiffParserService,
            $mockCommitHistoryService,
            $mockCarryforwardTagService
        );

        for ($i = 1; $i <= 2; ++$i) {
            if ($i > 1) {
                // After the first attempt, all subsequent ones should go into the in-memory cache
                // and not run any additional queries
                $mockQueryService->expects($this->never())
                    ->method('runQuery');
            }

            $coverageReport = $coverageAnalyserService->analyse($waypoint);

            $this->assertInstanceOf(
                CoverageReportInterface::class,
                $coverageReport
            );
            $this->assertSame(
                [
                    '1'
                ],
                $coverageReport->getUploads()
                    ->getSuccessfulUploads()
            );
            $this->assertEqualsWithDelta(
                100.0,
                $coverageReport->getCoveragePercentage(),
                PHP_FLOAT_EPSILON
            );
            $this->assertSame(
                1,
                $coverageReport->getTotalLines()
            );
            $this->assertSame(
                1,
                $coverageReport->getAtLeastPartiallyCoveredLines()
            );
            $this->assertSame(
                0,
                $coverageReport->getUncoveredLines()
            );
            $this->assertEqualsWithDelta(
                100.0,
                $coverageReport->getTagCoverage()
                    ->current()
                    ->getCoveragePercentage(),
                PHP_FLOAT_EPSILON
            );
            $this->assertSame(
                'mock-file',
                $coverageReport->getFileCoverage()
                    ->current()
                    ->getFileName()
            );
            $this->assertEqualsWithDelta(
                100.0,
                $coverageReport->getDiffCoveragePercentage(),
                PHP_FLOAT_EPSILON
            );
            $this->assertSame(
                0,
                $coverageReport->getDiffUncoveredLines()
            );
            $this->assertSame(
                'mock-file',
                $coverageReport->getLeastCoveredDiffFiles()
                    ->current()
                    ->getFileName()
            );
            $this->assertSame(
                LineState::COVERED,
                $coverageReport->getDiffLineCoverage()
                    ->current()
                    ->getState()
            );
            $this->assertSame(
                ['1'],
                $coverageReport->getUploads()
                    ->getSuccessfulUploads()
            );
        }
    }

    public function testNullableDiffCoveragePercentageIsStoredCorrectly(): void
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
            pullRequest: 12
        );

        $mockDiffParserService = $this->createMock(DiffParserServiceInterface::class);
        $mockDiffParserService->expects($this->once())
            ->method('get')
            ->with($waypoint)
            ->willReturn([]);

        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->never())
            ->method('runQuery');

        $coverageAnalyserService = new CachingCoverageAnalyserService(
            $mockQueryService,
            $mockDiffParserService,
            $this->createMock(CommitHistoryServiceInterface::class),
            $this->createMock(CarryforwardTagServiceInterface::class)
        );

        $coverageReport = $coverageAnalyserService->analyse($waypoint);

        $this->assertNull($coverageReport->getDiffCoveragePercentage());

        $this->assertNull($coverageReport->getDiffCoveragePercentage());
    }

    public function testNoUploadsShowsNoDiffCoverageNeeded(): void
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
            pullRequest: 12
        );

        $mockDiffParserService = $this->createMock(DiffParserServiceInterface::class);
        $mockDiffParserService->expects($this->once())
            ->method('get')
            ->with($waypoint)
            ->willReturn([
                'mock-file' => [1, 2]
            ]);

        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->willReturn(
            // No uploads on waypoint means no diff coverage possible
                new TotalUploadsQueryResult(
                    [],
                    [],
                    []
                )
            );

        $coverageAnalyserService = new CachingCoverageAnalyserService(
            $mockQueryService,
            $mockDiffParserService,
            $this->createMock(CommitHistoryServiceInterface::class),
            $this->createMock(CarryforwardTagServiceInterface::class)
        );

        $coverageReport = $coverageAnalyserService->analyse($waypoint);

        $this->assertNull($coverageReport->getDiffCoveragePercentage());

        $this->assertNull($coverageReport->getDiffCoveragePercentage());
    }

    public function testNoDiffOnWaypointToAnalyse(): void
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
            pullRequest: 12
        );

        $mockDiffParserService = $this->createMock(DiffParserServiceInterface::class);
        $mockDiffParserService->method('get')
            ->with($waypoint)
            ->willReturn([]);

        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->never())
            ->method('runQuery');

        $coverageAnalyserService = new CachingCoverageAnalyserService(
            $mockQueryService,
            $mockDiffParserService,
            $this->createMock(CommitHistoryServiceInterface::class),
            $this->createMock(CarryforwardTagServiceInterface::class)
        );

        $coverageReport = $coverageAnalyserService->analyse($waypoint);

        $this->assertSame(0, $coverageReport->getDiffUncoveredLines());
        $this->assertSame(0, $coverageReport->getDiffUncoveredLines());

        $this->assertEmpty($coverageReport->getLeastCoveredDiffFiles());
        $this->assertEmpty($coverageReport->getLeastCoveredDiffFiles());

        $this->assertEmpty($coverageReport->getDiffLineCoverage());
        $this->assertEmpty($coverageReport->getDiffLineCoverage());
    }

    private function getMockedQueryService(): MockObject|QueryService
    {
        $mockQueryService = $this->createMock(QueryServiceInterface::class);

        $mockQueryService->expects($this->atLeastOnce())
            ->method('runQuery')
            ->willReturnCallback(
                static fn(string $queryClass): QueryResultInterface => match ($queryClass) {
                    TotalUploadsQuery::class => new TotalUploadsQueryResult(
                        ['1'],
                        [new DateTimeImmutable('2024-01-03 00:00:00')],
                        [new Tag('mock-tag', 'mock-commit', [20])],
                    ),
                    TotalCoverageQuery::class => new TotalCoverageQueryResult(
                        100,
                        1,
                        1,
                        0,
                        0
                    ),
                    TotalTagCoverageQuery::class => new QueryResultIterator(
                        new ArrayIterator(
                            [
                                new TagCoverageQueryResult(
                                    new Tag('mock-tag', 'mock-commit', [20]),
                                    100,
                                    1,
                                    0,
                                    0,
                                    0
                                )
                            ]
                        ),
                        1,
                        static fn(QueryResultInterface $result): QueryResultInterface => $result
                    ),
                    FileCoverageQuery::class => new QueryResultIterator(
                        new ArrayIterator([
                            new FileCoverageQueryResult(
                                'mock-file',
                                100,
                                [1],
                                [1],
                                [],
                                []
                            )
                        ]),
                        1,
                        static fn(QueryResultInterface $result): QueryResultInterface => $result
                    ),
                    LineCoverageQuery::class => new QueryResultIterator(
                        new ArrayIterator([
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
                        1,
                        static fn(QueryResultInterface $result): QueryResultInterface => $result
                    ),
                    default => null,
                }
            );

        return $mockQueryService;
    }
}
