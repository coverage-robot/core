<?php

namespace App\Tests\Model;

use App\Model\CoverageReport;
use App\Model\ReportWaypoint;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;

final class CoverageReportTest extends TestCase
{
    public function testReportLazyLoading(): void
    {
        $totalUploads = new TotalUploadsQueryResult(['1'], [new DateTimeImmutable('2024-01-05 00:00:00')], []);
        $tagCoverage = new TagCoverageCollectionQueryResult([]);
        $leastCoveredDiffFiles = new FileCoverageCollectionQueryResult([]);
        $diffLineCoverage = new LineCoverageCollectionQueryResult([]);

        $report = new CoverageReport(
            new ReportWaypoint(
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: []
            ),
            static fn(): TotalUploadsQueryResult => $totalUploads,
            static fn(): int => 1,
            static fn(): int => 2,
            static fn(): int => 3,
            static fn(): float => 99.9,
            static fn(): TagCoverageCollectionQueryResult => $tagCoverage,
            static fn(): int => 95,
            static fn(): FileCoverageCollectionQueryResult => $leastCoveredDiffFiles,
            static fn(): int => 10,
            static fn(): LineCoverageCollectionQueryResult => $diffLineCoverage
        );

        $this->assertEquals(
            $totalUploads,
            $report->getUploads()
        );
        $this->assertEquals(
            new DateTimeImmutable('2024-01-05 00:00:00'),
            $report->getLatestSuccessfulUpload()
        );
        $this->assertEquals(
            1,
            $report->getTotalLines()
        );
        $this->assertEquals(
            2,
            $report->getAtLeastPartiallyCoveredLines()
        );
        $this->assertEquals(
            3,
            $report->getUncoveredLines()
        );
        $this->assertEquals(
            99.9,
            $report->getCoveragePercentage()
        );
        $this->assertEquals(
            $tagCoverage,
            $report->getTagCoverage()
        );
        $this->assertEquals(
            95,
            $report->getDiffCoveragePercentage()
        );
        $this->assertEquals(
            $leastCoveredDiffFiles,
            $report->getLeastCoveredDiffFiles()
        );
        $this->assertEquals(
            $diffLineCoverage,
            $report->getDiffLineCoverage()
        );
    }
}
