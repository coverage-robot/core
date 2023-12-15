<?php

namespace App\Tests\Service\Diff;

use App\Service\Diff\CachingDiffParserService;
use App\Service\Diff\DiffParserService;
use App\Model\ReportWaypoint;
use PHPUnit\Framework\TestCase;

class CachingDiffParserServiceTest extends TestCase
{
    public function testCachesRepeatedRequests(): void
    {
        $mockDiffParser = $this->createMock(DiffParserService::class);

        $mockDiffParser->expects($this->once())
            ->method('get')
            ->willReturn([]);

        $cachingDiffParser = new CachingDiffParserService($mockDiffParser);

        $mockWaypoint = $this->createMock(ReportWaypoint::class);

        $this->assertEquals([], $cachingDiffParser->get($mockWaypoint));
        $this->assertEquals([], $cachingDiffParser->get($mockWaypoint));
    }

    public function testDoesNotCacheDifferentUploads(): void
    {
        $mockDiffParser = $this->createMock(DiffParserService::class);

        $mockDiffParser->expects($this->exactly(2))
            ->method('get')
            ->willReturn([]);

        $cachingDiffParser = new CachingDiffParserService($mockDiffParser);

        $this->assertEquals([], $cachingDiffParser->get($this->createMock(ReportWaypoint::class)));
        $this->assertEquals([], $cachingDiffParser->get($this->createMock(ReportWaypoint::class)));
    }
}
