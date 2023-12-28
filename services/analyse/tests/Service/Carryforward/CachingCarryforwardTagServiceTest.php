<?php

namespace App\Tests\Service\Carryforward;

use App\Model\ReportWaypoint;
use App\Service\Carryforward\CachingCarryforwardTagService;
use App\Service\Carryforward\CarryforwardTagService;
use Packages\Contracts\Tag\Tag;
use PHPUnit\Framework\TestCase;

class CachingCarryforwardTagServiceTest extends TestCase
{
    public function testCachesRepeatedRequests(): void
    {
        $carryforwardTags = [new Tag('tag', 'commit')];

        $mockCarryforwardTagService = $this->createMock(CarryforwardTagService::class);

        $mockCarryforwardTagService->expects($this->once())
            ->method('getTagsToCarryforward')
            ->willReturn($carryforwardTags);

        $cachingCarryforwardTagService = new CachingCarryforwardTagService($mockCarryforwardTagService);

        $mockWaypoint = $this->createMock(ReportWaypoint::class);

        $this->assertEquals(
            $carryforwardTags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                $mockWaypoint,
                [
                    new Tag('tag', 'commit')
                ]
            )
        );
        $this->assertEquals(
            $carryforwardTags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                $mockWaypoint,
                [
                    new Tag('tag', 'commit')
                ]
            )
        );
    }

    public function testDoesNotCacheDifferentUploads(): void
    {
        $tags = [new Tag('tag', 'commit')];

        $mockCarryforwardTagService = $this->createMock(CarryforwardTagService::class);

        $mockCarryforwardTagService->expects($this->exactly(2))
            ->method('getTagsToCarryforward')
            ->willReturn($tags);

        $cachingCarryforwardTagService = new CachingCarryforwardTagService($mockCarryforwardTagService);

        $this->assertEquals(
            $tags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                $this->createMock(ReportWaypoint::class),
                [new Tag('tag', 'commit')]
            )
        );
        $this->assertEquals(
            $tags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                $this->createMock(ReportWaypoint::class),
                [new Tag('tag', 'commit')]
            )
        );
    }
}
