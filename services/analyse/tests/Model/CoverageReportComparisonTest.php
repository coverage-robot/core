<?php

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
    #[DataProvider('coverageReportDataProvider')]
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

    public static function coverageReportDataProvider(): array
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
                    uploads: new TotalUploadsQueryResult(['1'], [], [], null),
                    totalLines: 1,
                    atLeastPartiallyCoveredLines: 2,
                    uncoveredLines: 3,
                    coveragePercentage: 80,
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
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
                    uploads: new TotalUploadsQueryResult(['1'], [], [], null),
                    totalLines: 1,
                    atLeastPartiallyCoveredLines: 2,
                    uncoveredLines: 3,
                    coveragePercentage: 80,
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
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
                    uploads: new TotalUploadsQueryResult(['1'], [], [], null),
                    totalLines: 1,
                    atLeastPartiallyCoveredLines: 2,
                    uncoveredLines: 3,
                    coveragePercentage: 80,
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
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
                    uploads: new TotalUploadsQueryResult(['1'], [], [], null),
                    totalLines: 1,
                    atLeastPartiallyCoveredLines: 2,
                    uncoveredLines: 3,
                    coveragePercentage: 70,
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
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
                    uploads: new TotalUploadsQueryResult(['1'], [], [], null),
                    totalLines: 1,
                    atLeastPartiallyCoveredLines: 2,
                    uncoveredLines: 3,
                    coveragePercentage: 56.67,
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
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
                    uploads: new TotalUploadsQueryResult(['1'], [], [], null),
                    totalLines: 1,
                    atLeastPartiallyCoveredLines: 2,
                    uncoveredLines: 3,
                    coveragePercentage: 56.34,
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
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
                    uploads: new TotalUploadsQueryResult(['1'], [], [], null),
                    totalLines: 1,
                    atLeastPartiallyCoveredLines: 2,
                    uncoveredLines: 3,
                    coveragePercentage: 56.67,
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
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
                    uploads: new TotalUploadsQueryResult(['1'], [], [], null),
                    totalLines: 1,
                    atLeastPartiallyCoveredLines: 2,
                    uncoveredLines: 3,
                    coveragePercentage: 61.97,
                    tagCoverage: new TagCoverageCollectionQueryResult([]),
                    diffCoveragePercentage: 95,
                    leastCoveredDiffFiles: new FileCoverageCollectionQueryResult([]),
                    diffLineCoverage: new LineCoverageCollectionQueryResult([]),
                ),
                5.3
            ],
        ];
    }
}
