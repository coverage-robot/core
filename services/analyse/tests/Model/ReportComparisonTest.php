<?php

namespace App\Tests\Model;

use App\Model\Report;
use App\Model\ReportComparison;
use App\Model\ReportWaypoint;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReportComparisonTest extends TestCase
{
    #[DataProvider('coverageReportDataProvider')]
    public function testComparingCoveragePercentageChanges(
        Report $baseReport,
        Report $headReport,
        float $expectedPercentageChange
    ): void {
        $comparison = new ReportComparison(
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
                new Report(
                    new ReportWaypoint(
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-ref',
                        'mock-commit',
                        null
                    ),
                    new TotalUploadsQueryResult(['1'], [], null),
                    1,
                    2,
                    3,
                    80,
                    new TagCoverageCollectionQueryResult([]),
                    95,
                    new FileCoverageCollectionQueryResult([]),
                    new LineCoverageCollectionQueryResult([]),
                    []
                ),
                new Report(
                    new ReportWaypoint(
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-ref',
                        'mock-commit',
                        null
                    ),
                    new TotalUploadsQueryResult(['1'], [], null),
                    1,
                    2,
                    3,
                    80,
                    new TagCoverageCollectionQueryResult([]),
                    95,
                    new FileCoverageCollectionQueryResult([]),
                    new LineCoverageCollectionQueryResult([]),
                    []
                ),
                0
            ],
            '-10% change in total coverage' => [
                new Report(
                    new ReportWaypoint(
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-ref',
                        'mock-commit',
                        null
                    ),
                    new TotalUploadsQueryResult(['1'], [], null),
                    1,
                    2,
                    3,
                    80,
                    new TagCoverageCollectionQueryResult([]),
                    95,
                    new FileCoverageCollectionQueryResult([]),
                    new LineCoverageCollectionQueryResult([]),
                    []
                ),
                new Report(
                    new ReportWaypoint(
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-ref',
                        'mock-commit',
                        null
                    ),
                    new TotalUploadsQueryResult(['1'], [], null),
                    1,
                    2,
                    3,
                    70,
                    new TagCoverageCollectionQueryResult([]),
                    95,
                    new FileCoverageCollectionQueryResult([]),
                    new LineCoverageCollectionQueryResult([]),
                    []
                ),
                -10
            ],
            '-0.33% change in total coverage' => [
                new Report(
                    new ReportWaypoint(
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-ref',
                        'mock-commit',
                        null
                    ),
                    new TotalUploadsQueryResult(['1'], [], null),
                    1,
                    2,
                    3,
                    56.67,
                    new TagCoverageCollectionQueryResult([]),
                    95,
                    new FileCoverageCollectionQueryResult([]),
                    new LineCoverageCollectionQueryResult([]),
                    []
                ),
                new Report(
                    new ReportWaypoint(
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-ref',
                        'mock-commit',
                        null
                    ),
                    new TotalUploadsQueryResult(['1'], [], null),
                    1,
                    2,
                    3,
                    56.34,
                    new TagCoverageCollectionQueryResult([]),
                    95,
                    new FileCoverageCollectionQueryResult([]),
                    new LineCoverageCollectionQueryResult([]),
                    []
                ),
                -0.33
            ],
            '+5.3% change in total coverage' => [
                new Report(
                    new ReportWaypoint(
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-ref',
                        'mock-commit',
                        null
                    ),
                    new TotalUploadsQueryResult(['1'], [], null),
                    1,
                    2,
                    3,
                    56.67,
                    new TagCoverageCollectionQueryResult([]),
                    95,
                    new FileCoverageCollectionQueryResult([]),
                    new LineCoverageCollectionQueryResult([]),
                    []
                ),
                new Report(
                    new ReportWaypoint(
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-ref',
                        'mock-commit',
                        null
                    ),
                    new TotalUploadsQueryResult(['1'], [], null),
                    1,
                    2,
                    3,
                    61.97,
                    new TagCoverageCollectionQueryResult([]),
                    95,
                    new FileCoverageCollectionQueryResult([]),
                    new LineCoverageCollectionQueryResult([]),
                    []
                ),
                5.3
            ],
        ];
    }
}
