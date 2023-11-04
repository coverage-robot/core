<?php

namespace App\Tests\Service\Carryforward;

use App\Service\Carryforward\CachingCarryforwardTagService;
use App\Service\Carryforward\CarryforwardTagService;
use Packages\Event\Model\Upload;
use Packages\Models\Model\Tag;
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

        $mockUpload = $this->createMock(Upload::class);

        $this->assertEquals(
            $carryforwardTags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                $mockUpload,
                [
                    new Tag('tag', 'commit')
                ]
            )
        );
        $this->assertEquals(
            $carryforwardTags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                $mockUpload,
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
                $this->createMock(Upload::class),
                [new Tag('tag', 'commit')]
            )
        );
        $this->assertEquals(
            $tags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                $this->createMock(Upload::class),
                [new Tag('tag', 'commit')]
            )
        );
    }
}
