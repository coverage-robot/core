<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Model\CoverageReport;
use App\Model\CoverageReportComparison;
use App\Model\ReportWaypoint;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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

        $this->assertEquals(
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

        $this->assertEquals(
            $expectedUncoveredLineChange,
            $comparison->getUncoveredLinesChange()
        );
    }

    public static function coverageChangesDataProvider(): array
    {
        return [
            'No change in total coverage' => [
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                0
            ],
            '-10% change in total coverage' => [
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                -10
            ],
            '-0.33% change in total coverage' => [
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                -0.33
            ],
            '+5.3% change in total coverage' => [
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([]),
                ),
                5.3
            ],
        ];
    }

    public static function uncoveredLineChangesDataProvider(): array
    {
        return [
            '+2 change in uncovered lines' => [
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                2
            ],
            '-2 change in uncovered lines' => [
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                -2
            ],
            'No change in uncovered lines' => [
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([])
                ),
                new CoverageReport(
                    waypoint: new ReportWaypoint(
                        provider: Provider::GITHUB,
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
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffUncoveredLines: static fn(): int => 0,
                    diffLineCoverage: new LineCoverageCollectionQueryResult([]),
                ),
                0
            ],
        ];
    }
}
