<?php

namespace App\Tests\Model;

use App\Model\Report;
use App\Model\ReportWaypoint;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase
{
    public function testReportLazyLoading(): void
    {
        $mockWaypoint = $this->createMock(ReportWaypoint::class);

        $totalUploads = new TotalUploadsQueryResult(['1'], [], [], null);
        $tagCoverage = new TagCoverageCollectionQueryResult([]);
        $leastCoveredDiffFiles = new FileCoverageCollectionQueryResult([]);
        $diffLineCoverage = new LineCoverageCollectionQueryResult([]);

        $report = new Report(
            $mockWaypoint,
            static fn() => $totalUploads,
            static fn() => 1,
            static fn() => 2,
            static fn() => 3,
            static fn() => 99.9,
            static fn() => $tagCoverage,
            static fn() => 95,
            static fn() => $leastCoveredDiffFiles,
            static fn() => $diffLineCoverage
        );

        $this->assertEquals(
            $totalUploads,
            $report->getUploads()
        );
        $this->assertNull($report->getLatestSuccessfulUpload());
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
