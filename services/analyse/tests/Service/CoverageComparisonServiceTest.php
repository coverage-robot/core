<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Model\CoverageReport;
use App\Model\CoverageReportComparison;
use App\Model\ReportWaypoint;
use App\Service\CachingCoverageAnalyserService;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\CoverageComparisonService;
use App\Service\Diff\DiffParserServiceInterface;
use App\Service\History\CommitHistoryServiceInterface;
use App\Service\QueryServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\UploadsFinalised;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CoverageComparisonServiceTest extends TestCase
{
    public function testGettingBaseWaypointFromWaypointHistory(): void
    {
        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [
                [
                    'commit' => 'mock-commit-1',
                    'ref' => 'mock-ref',
                    'merged' => false
                ],
                [
                    'commit' => 'mock-commit-2',
                    'ref' => 'mock-ref',
                    'merged' => false
                ],
                [
                    'commit' => 'mock-commit-3',
                    'ref' => null,
                    'merged' => true
                ]
            ],
            diff: []
        );
        $mockCoverageReport = new CoverageReport(
            waypoint: $headWaypoint,
            uploads: static fn(): null => null,
            size: 200,
            totalLines: 100,
            atLeastPartiallyCoveredLines: 50,
            uncoveredLines: 50,
            coveragePercentage: 50.0,
            fileCoverage: static fn(): null => null,
            tagCoverage: static fn(): null => null,
            diffCoveragePercentage: 50.0,
            leastCoveredDiffFiles: static fn(): null => null,
            diffUncoveredLines: static fn(): int => 0,
            diffLineCoverage: static fn(): null => null,
        );

        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
            projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-branch',
            commit: 'mock-commit',
            parent: [],
            baseCommit: 'mock-base-commit',
            baseRef: 'mock-base-ref',
        );

        $coverageComparisonService = new CoverageComparisonService(
            new NullLogger(),
            new CachingCoverageAnalyserService(
                $this->createStub(QueryServiceInterface::class),
                $this->createStub(DiffParserServiceInterface::class),
                $this->createStub(CommitHistoryServiceInterface::class),
                $this->createStub(CarryforwardTagServiceInterface::class)
            )
        );

        $comparison = $coverageComparisonService->getComparisonForCoverageReport(
            $mockCoverageReport,
            $event
        );
        $this->assertInstanceof(CoverageReportComparison::class, $comparison);

        $this->assertSame(
            'mock-commit-3',
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getCommit()
        );
        $this->assertSame(
            'mock-base-ref',
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getRef()
        );
    }

    public function testGettingBaseWaypointFromEventBase(): void
    {
        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
            pullRequest: 1
        );
        $mockCoverageReport = new CoverageReport(
            waypoint: $headWaypoint,
            uploads: static fn(): null => null,
            size: 200,
            totalLines: 100,
            atLeastPartiallyCoveredLines: 50,
            uncoveredLines: 50,
            coveragePercentage: 50.0,
            fileCoverage: static fn(): null => null,
            tagCoverage: static fn(): null => null,
            diffCoveragePercentage: 50.0,
            leastCoveredDiffFiles: static fn(): null => null,
            diffUncoveredLines: static fn(): int => 0,
            diffLineCoverage: static fn(): null => null,
        );

        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
            projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-branch',
            commit: 'mock-commit',
            parent: [],
            pullRequest: 1,
            baseCommit: 'mock-base-commit',
            baseRef: 'mock-base-ref'
        );

        $coverageComparisonService = new CoverageComparisonService(
            new NullLogger(),
            new CachingCoverageAnalyserService(
                $this->createStub(QueryServiceInterface::class),
                $this->createStub(DiffParserServiceInterface::class),
                $this->createStub(CommitHistoryServiceInterface::class),
                $this->createStub(CarryforwardTagServiceInterface::class)
            )
        );

        $comparison = $coverageComparisonService->getComparisonForCoverageReport(
            $mockCoverageReport,
            $event
        );
        $this->assertInstanceof(CoverageReportComparison::class, $comparison);

        $this->assertSame(
            'mock-base-commit',
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getCommit()
        );
        $this->assertSame(
            'mock-base-ref',
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getRef()
        );
    }

    public function testGettingBaseWaypointFromEventParent(): void
    {
        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
            pullRequest: 1
        );
        $mockCoverageReport = new CoverageReport(
            waypoint: $headWaypoint,
            uploads: static fn(): null => null,
            size: 200,
            totalLines: 100,
            atLeastPartiallyCoveredLines: 50,
            uncoveredLines: 50,
            coveragePercentage: 50.0,
            fileCoverage: static fn(): null => null,
            tagCoverage: static fn(): null => null,
            diffCoveragePercentage: 50.0,
            leastCoveredDiffFiles: static fn(): null => null,
            diffUncoveredLines: static fn(): int => 0,
            diffLineCoverage: static fn(): null => null,
        );

        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
            projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-branch',
            commit: 'mock-commit',
            parent: ['mock-merge-commit-parent-1', 'mock-merge-commit-parent-2'],
        );

        $coverageComparisonService = new CoverageComparisonService(
            new NullLogger(),
            new CachingCoverageAnalyserService(
                $this->createStub(QueryServiceInterface::class),
                $this->createStub(DiffParserServiceInterface::class),
                $this->createStub(CommitHistoryServiceInterface::class),
                $this->createStub(CarryforwardTagServiceInterface::class)
            )
        );

        $comparison = $coverageComparisonService->getComparisonForCoverageReport(
            $mockCoverageReport,
            $event
        );
        $this->assertInstanceof(CoverageReportComparison::class, $comparison);

        $this->assertSame(
            'mock-merge-commit-parent-1',
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getCommit()
        );
        $this->assertSame(
            'mock-branch',
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getRef()
        );
    }

    public function testUnableToCompareWaypoint(): void
    {
        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
        );
        $mockCoverageReport = new CoverageReport(
            waypoint: $headWaypoint,
            uploads: static fn(): null => null,
            size: 200,
            totalLines: 100,
            atLeastPartiallyCoveredLines: 50,
            uncoveredLines: 50,
            coveragePercentage: 50.0,
            fileCoverage: static fn(): null => null,
            tagCoverage: static fn(): null => null,
            diffCoveragePercentage: 50.0,
            leastCoveredDiffFiles: static fn(): null => null,
            diffUncoveredLines: static fn(): int => 0,
            diffLineCoverage: static fn(): null => null,
        );

        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
            projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-branch',
            commit: 'mock-commit',
            parent: [],
        );

        $coverageComparisonService = new CoverageComparisonService(
            new NullLogger(),
            new CachingCoverageAnalyserService(
                $this->createStub(QueryServiceInterface::class),
                $this->createStub(DiffParserServiceInterface::class),
                $this->createStub(CommitHistoryServiceInterface::class),
                $this->createStub(CarryforwardTagServiceInterface::class)
            )
        );

        $comparison = $coverageComparisonService->getComparisonForCoverageReport(
            $mockCoverageReport,
            $event
        );

        $this->assertNotInstanceOf(CoverageReportComparison::class, $comparison);
    }

    public function testGettingBaseWaypointFromWaypointHistoryWillAbideByMaxCommits(): void
    {
        $historyPagesRequested = [];

        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: function (ReportWaypoint $waypoint, int $page) use (&$historyPagesRequested): array {
                $historyPagesRequested[] = $page;

                // Return all non-merged PRs, so that the history (before maxing out) does not
                // resolve to a merge base.
                return array_fill(
                    0,
                    (int)ceil(CoverageComparisonService::MAX_COMMIT_HISTORY_COMMITS / 2),
                    [
                        'commit' => 'mock-commit-1',
                        'ref' => 'mock-ref',
                        'merged' => false
                    ]
                );
            },
            diff: []
        );
        $mockCoverageReport = new CoverageReport(
            waypoint: $headWaypoint,
            uploads: static fn(): null => null,
            size: 200,
            totalLines: 100,
            atLeastPartiallyCoveredLines: 50,
            uncoveredLines: 50,
            coveragePercentage: 50.0,
            fileCoverage: static fn(): null => null,
            tagCoverage: static fn(): null => null,
            diffCoveragePercentage: 50.0,
            leastCoveredDiffFiles: static fn(): null => null,
            diffUncoveredLines: static fn(): int => 0,
            diffLineCoverage: static fn(): null => null,
        );

        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
            projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-branch',
            commit: 'mock-commit',
            parent: [],
            baseCommit: 'mock-base-commit',
            baseRef: 'mock-base-ref',
        );

        $mockCommitHistoryService = $this->createStub(CommitHistoryServiceInterface::class);

        $coverageComparisonService = new CoverageComparisonService(
            new NullLogger(),
            new CachingCoverageAnalyserService(
                $this->createStub(QueryServiceInterface::class),
                $this->createStub(DiffParserServiceInterface::class),
                $mockCommitHistoryService,
                $this->createStub(CarryforwardTagServiceInterface::class)
            )
        );

        $comparison = $coverageComparisonService->getComparisonForCoverageReport(
            $mockCoverageReport,
            $event
        );

        // We should never be requesting more than page 2, because we're
        // providing the maximum number of commits by the second  page
        $this->assertSame(
            [1, 2],
            $historyPagesRequested,
            'The only pages of commit history requested should be 1 and 2.'
        );

        $this->assertNotInstanceOf(
            CoverageReportComparison::class,
            $comparison,
            'The comparison should not return a result as no base ref should have been reached'
        );
    }
}
