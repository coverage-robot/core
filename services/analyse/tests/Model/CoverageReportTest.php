<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Model\CoverageReport;
use App\Model\ReportWaypoint;
use App\Query\Result\QueryResultIterator;
use App\Query\Result\TotalUploadsQueryResult;
use ArrayIterator;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CoverageReportTest extends TestCase
{
    public function testReportLazyLoading(): void
    {
        $totalUploads = new TotalUploadsQueryResult(['1'], [new DateTimeImmutable('2024-01-05 00:00:00')], []);
        $tagCoverage = new QueryResultIterator(
            new ArrayIterator([]),
            0,
            static fn(): never => throw new RuntimeException('Should never be called')
        );
        $leastCoveredDiffFiles = new QueryResultIterator(
            new ArrayIterator([]),
            0,
            static fn(): never => throw new RuntimeException('Should never be called')
        );
        $fileCoverage = new QueryResultIterator(
            new ArrayIterator([]),
            0,
            static fn(): never => throw new RuntimeException('Should never be called')
        );
        $diffLineCoverage = new QueryResultIterator(
            new ArrayIterator([]),
            0,
            static fn(): never => throw new RuntimeException('Should never be called')
        );

        $report = new CoverageReport(
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: []
            ),
            static fn(): TotalUploadsQueryResult => $totalUploads,
            static fn(): int => 12,
            static fn(): int => 1,
            static fn(): int => 2,
            static fn(): int => 3,
            static fn(): float => 99.9,
            static fn(): QueryResultIterator => $fileCoverage,
            static fn(): QueryResultIterator => $tagCoverage,
            static fn(): int => 95,
            static fn(): QueryResultIterator => $leastCoveredDiffFiles,
            static fn(): int => 10,
            static fn(): QueryResultIterator => $diffLineCoverage
        );

        $this->assertEquals(
            $totalUploads,
            $report->getUploads()
        );
        $this->assertEquals(
            new DateTimeImmutable('2024-01-05 00:00:00'),
            $report->getLatestSuccessfulUpload()
        );
        $this->assertSame(
            1,
            $report->getTotalLines()
        );
        $this->assertSame(
            2,
            $report->getAtLeastPartiallyCoveredLines()
        );
        $this->assertSame(
            3,
            $report->getUncoveredLines()
        );
        $this->assertEqualsWithDelta(
            99.9,
            $report->getCoveragePercentage(),
            PHP_FLOAT_EPSILON
        );
        $this->assertEquals(
            $fileCoverage,
            $report->getFileCoverage()
        );
        $this->assertEquals(
            $tagCoverage,
            $report->getTagCoverage()
        );
        $this->assertEqualsWithDelta(
            95.0,
            $report->getDiffCoveragePercentage(),
            PHP_FLOAT_EPSILON
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
