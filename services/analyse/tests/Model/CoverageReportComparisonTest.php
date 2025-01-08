<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Model\CoverageReport;
use App\Model\CoverageReportComparison;
use App\Model\ReportWaypoint;
use App\Query\Result\QueryResultIterator;
use App\Query\Result\TotalUploadsQueryResult;
use ArrayIterator;
use Iterator;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CoverageReportComparisonTest extends TestCase
{
    #[DataProvider('coverageChangesDataProvider')]
    public function testComparingCoveragePercentageChanges(
        CoverageReport $baseReport,
        CoverageReport $headReport,
        float $expectedPercentageChange
    ): void {
        $comparison = new CoverageReportComparison(
            $baseReport,
            $headReport
        );

        $this->assertSame(
            $expectedPercentageChange,
            $comparison->getCoverageChange()
        );
    }

    #[DataProvider('uncoveredLineChangesDataProvider')]
    public function testComparingUncoveredLinesChange(
        CoverageReport $baseReport,
        CoverageReport $headReport,
        int $expectedUncoveredLineChange
    ): void {
        $comparison = new CoverageReportComparison(
            $baseReport,
            $headReport
        );

        $this->assertSame(
            $expectedUncoveredLineChange,
            $comparison->getUncoveredLinesChange()
        );
    }

    public static function coverageChangesDataProvider(): Iterator
    {
        yield 'No change in total coverage' => [
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 3,
                coveragePercentage: 80,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 3,
                coveragePercentage: 80,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            0
        ];

        yield '-10% change in total coverage' => [
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 3,
                coveragePercentage: 80,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 3,
                coveragePercentage: 70,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            -10
        ];

        yield '-0.33% change in total coverage' => [
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 3,
                coveragePercentage: 56.67,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 3,
                coveragePercentage: 56.34,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            -0.33
        ];

        yield '+5.3% change in total coverage' => [
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 3,
                coveragePercentage: 56.67,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 3,
                coveragePercentage: 61.97,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
            ),
            5.3

        ];
    }

    public static function uncoveredLineChangesDataProvider(): Iterator
    {
        yield '+2 change in uncovered lines' => [
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 4,
                coveragePercentage: 56.67,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 6,
                coveragePercentage: 56.34,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            2
        ];

        yield '-2 change in uncovered lines' => [
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 4,
                coveragePercentage: 56.67,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 2,
                coveragePercentage: 56.34,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            -2
        ];

        yield 'No change in uncovered lines' => [
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 4,
                coveragePercentage: 56.67,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            new CoverageReport(
                waypoint: new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                uploads: new TotalUploadsQueryResult(['1'], [], []),
                size: 2,
                totalLines: 1,
                atLeastPartiallyCoveredLines: 2,
                uncoveredLines: 4,
                coveragePercentage: 61.97,
                fileCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                tagCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffCoveragePercentage: 95,
                leastCoveredDiffFiles: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                diffUncoveredLines: static fn(): int => 0,
                diffLineCoverage: new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
            ),
            0

        ];
    }
}
