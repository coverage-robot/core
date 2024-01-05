<?php

namespace App\Tests\Service;

use App\Model\CoverageReport;
use App\Model\ReportWaypoint;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\CoverageAnalyserService;
use App\Service\CoverageComparisonService;
use App\Service\Diff\DiffParserService;
use App\Service\History\CommitHistoryService;
use App\Service\QueryService;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\UploadsFinalised;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoverageComparisonServiceTest extends TestCase
{
    public function testGettingBaseWaypointFromWaypointHistory(): void
    {
        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
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
            diff: [],
            pullRequest: null
        );
        $mockCoverageReport = $this->createMock(CoverageReport::class);
        $mockCoverageReport->expects($this->atLeastOnce())
            ->method('getWaypoint')
            ->willReturn($headWaypoint);

        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
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
            new CoverageAnalyserService(
                $this->createMock(QueryService::class),
                $this->createMock(DiffParserService::class),
                $this->createMock(CommitHistoryService::class),
                $this->createMock(CarryforwardTagService::class)
            )
        );

        $comparison = $coverageComparisonService->getComparisonForCoverageReport(
            $mockCoverageReport,
            $event
        );

        $this->assertEquals(
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getCommit(),
            'mock-commit-3'
        );
        $this->assertEquals(
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getRef(),
            'mock-base-ref'
        );
    }

    public function testGettingBaseWaypointFromEventBase(): void
    {
        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
            pullRequest: 1
        );
        $mockCoverageReport = $this->createMock(CoverageReport::class);
        $mockCoverageReport->expects($this->atLeastOnce())
            ->method('getWaypoint')
            ->willReturn($headWaypoint);

        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
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
            new CoverageAnalyserService(
                $this->createMock(QueryService::class),
                $this->createMock(DiffParserService::class),
                $this->createMock(CommitHistoryService::class),
                $this->createMock(CarryforwardTagService::class)
            )
        );

        $comparison = $coverageComparisonService->getComparisonForCoverageReport(
            $mockCoverageReport,
            $event
        );

        $this->assertEquals(
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getCommit(),
            'mock-base-commit'
        );
        $this->assertEquals(
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getRef(),
            'mock-base-ref'
        );
    }

    public function testGettingBaseWaypointFromEventParent(): void
    {
        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
            pullRequest: 1
        );
        $mockCoverageReport = $this->createMock(CoverageReport::class);
        $mockCoverageReport->expects($this->atLeastOnce())
            ->method('getWaypoint')
            ->willReturn($headWaypoint);

        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-branch',
            commit: 'mock-commit',
            parent: ['mock-merge-commit-parent-1', 'mock-merge-commit-parent-2'],
        );

        $coverageComparisonService = new CoverageComparisonService(
            new NullLogger(),
            new CoverageAnalyserService(
                $this->createMock(QueryService::class),
                $this->createMock(DiffParserService::class),
                $this->createMock(CommitHistoryService::class),
                $this->createMock(CarryforwardTagService::class)
            )
        );

        $comparison = $coverageComparisonService->getComparisonForCoverageReport(
            $mockCoverageReport,
            $event
        );

        $this->assertEquals(
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getCommit(),
            'mock-merge-commit-parent-1'
        );
        $this->assertEquals(
            $comparison->getBaseReport()
                ->getWaypoint()
                ->getRef(),
            'mock-branch'
        );
    }

    public function testUnableToCompareWaypoint(): void
    {
        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
        );
        $mockCoverageReport = $this->createMock(CoverageReport::class);
        $mockCoverageReport->expects($this->never())
            ->method('getWaypoint')
            ->willReturn($headWaypoint);

        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-branch',
            commit: 'mock-commit',
            parent: [],
        );

        $coverageComparisonService = new CoverageComparisonService(
            new NullLogger(),
            new CoverageAnalyserService(
                $this->createMock(QueryService::class),
                $this->createMock(DiffParserService::class),
                $this->createMock(CommitHistoryService::class),
                $this->createMock(CarryforwardTagService::class)
            )
        );

        $comparison = $coverageComparisonService->getComparisonForCoverageReport(
            $mockCoverageReport,
            $event
        );

        $this->assertNull($comparison);
    }
}
