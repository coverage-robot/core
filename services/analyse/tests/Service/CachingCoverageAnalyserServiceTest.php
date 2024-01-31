<?php

namespace App\Tests\Service;

use App\Model\ReportWaypoint;
use App\Service\CachingCoverageAnalyserService;
use App\Service\CoverageAnalyserServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;

final class CachingCoverageAnalyserServiceTest extends TestCase
{
    public function testCachingAndLazyLoadingReportMetrics(): void
    {
        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserServiceInterface::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getCoveragePercentage')
            ->willReturn(95.6);

        $cachingCoverageAnalyserService = new CachingCoverageAnalyserService($mockCoverageAnalyserService);

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

        // We never perform another query on the same waypoint a second time
        $report = $cachingCoverageAnalyserService->analyse($mockWaypoint);

        $this->assertEquals(
            95.6,
            $report->getCoveragePercentage()
        );
    }

    public function testCachingDiffCoveragePercentage(): void
    {
        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserServiceInterface::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getDiffCoveragePercentage')
            ->willReturn(null);

        $cachingCoverageAnalyserService = new CachingCoverageAnalyserService($mockCoverageAnalyserService);

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

        $this->assertNull($report->getDiffCoveragePercentage());

        // We never perform another query on the same waypoint a second time
        $report = $cachingCoverageAnalyserService->analyse($mockWaypoint);

        $this->assertNull($report->getDiffCoveragePercentage());
    }
}
