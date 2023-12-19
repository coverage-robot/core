<?php

namespace App\Tests\Service;

use App\Model\ReportInterface;
use App\Model\ReportWaypoint;
use App\Query\FileCoverageQuery;
use App\Query\LineCoverageQuery;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\CoverageAnalyserService;
use App\Service\Diff\DiffParserService;
use App\Service\History\CommitHistoryServiceInterface;
use App\Service\QueryService;
use Packages\Contracts\Provider\Provider;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CoverageAnalyserServiceTest extends TestCase
{
    public function testAnalysingWaypoint(): void
    {
        $waypoint = new ReportWaypoint(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            12,
            [],
            []
        );

        $mockDiffParserService = $this->createMock(DiffParserService::class);
        $mockDiffParserService->expects($this->atLeastOnce())
            ->method('get')
            ->with($waypoint)
            ->willReturn([
                'mock-file' => [1,2,3]
            ]);

        $mockCommitHistoryService = $this->createMock(CommitHistoryServiceInterface::class);

        $mockCarryforwardTagService = $this->createMock(CarryforwardTagService::class);

        $coverageAnalyserService = new CoverageAnalyserService(
            $this->getMockedQueryService(),
            $mockDiffParserService,
            $mockCommitHistoryService,
            $mockCarryforwardTagService
        );

        $coverageReport = $coverageAnalyserService->analyse($waypoint);

        $this->assertInstanceOf(
            ReportInterface::class,
            $coverageReport
        );
        $this->assertEquals(
            100,
            $coverageReport->getCoveragePercentage()
        );
        $this->assertEquals(
            1,
            $coverageReport->getTotalLines()
        );
        $this->assertEquals(
            1,
            $coverageReport->getAtLeastPartiallyCoveredLines()
        );
        $this->assertEquals(
            0,
            $coverageReport->getUncoveredLines()
        );
        $this->assertEquals(
            100,
            $coverageReport->getTagCoverage()
                ->getTags()[0]
                ->getCoveragePercentage()
        );
        $this->assertEquals(
            100,
            $coverageReport->getDiffCoveragePercentage()
        );
        $this->assertEquals(
            'mock-file',
            $coverageReport->getLeastCoveredDiffFiles()
                ->getFiles()[0]
                ->getFileName()
        );
        $this->assertEquals(
            LineState::COVERED,
            $coverageReport->getDiffLineCoverage()
                ->getLines()[0]
                ->getState()
        );
        $this->assertEquals(
            ['1'],
            $coverageReport->getUploads()
                ->getSuccessfulUploads()
        );
    }

    private function getMockedQueryService(): MockObject|QueryService
    {
        $mockQueryService = $this->createMock(QueryService::class);

        $mockQueryService->expects($this->atLeastOnce())
            ->method('runQuery')
            ->willReturnCallback(
                static fn(string $queryClass) => match ($queryClass) {
                    TotalUploadsQuery::class => new TotalUploadsQueryResult(
                        ['1'],
                        [new Tag('mock-tag', 'mock-commit')],
                        null
                    ),
                    TotalCoverageQuery::class => new CoverageQueryResult(
                        100,
                        1,
                        1,
                        0,
                        0
                    ),
                    TotalTagCoverageQuery::class => new TagCoverageCollectionQueryResult(
                        [
                            new TagCoverageQueryResult(
                                new Tag('mock-tag', 'mock-commit'),
                                100,
                                1,
                                0,
                                0,
                                0
                            )
                        ]
                    ),
                    FileCoverageQuery::class => new FileCoverageCollectionQueryResult(
                        [
                            new FileCoverageQueryResult(
                                'mock-file',
                                100,
                                1,
                                1,
                                0,
                                0
                            )
                        ]
                    ),
                    LineCoverageQuery::class => new LineCoverageCollectionQueryResult([
                        new LineCoverageQueryResult(
                            'mock-file',
                            1,
                            LineState::COVERED,
                            false,
                            false,
                            true,
                            0,
                            0
                        )
                    ]),
                    default => null,
                }
            );

        return $mockQueryService;
    }
}
