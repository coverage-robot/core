<?php

namespace App\Tests\Service\Carryforward;

use App\Model\ReportWaypoint;
use App\Service\Carryforward\CachingCarryforwardTagService;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use PHPUnit\Framework\TestCase;

final class CachingCarryforwardTagServiceTest extends TestCase
{
    public function testCachesRepeatedRequests(): void
    {
        $carryforwardTags = [new Tag('tag', 'commit', [1])];

        $mockCarryforwardTagService = $this->createMock(CarryforwardTagServiceInterface::class);

        $mockCarryforwardTagService->expects($this->once())
            ->method('getTagsToCarryforward')
            ->willReturn($carryforwardTags);

        $cachingCarryforwardTagService = new CachingCarryforwardTagService($mockCarryforwardTagService);

        $mockWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
            pullRequest: 1
        );

        $this->assertEquals(
            $carryforwardTags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                $mockWaypoint,
                [
                    new Tag('tag', 'commit', [1])
                ]
            )
        );
        $this->assertEquals(
            $carryforwardTags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                $mockWaypoint,
                [
                    new Tag('tag', 'commit', [1])
                ]
            )
        );
    }

    public function testDoesNotCacheDifferentUploads(): void
    {
        $tags = [new Tag('tag', 'commit', [1])];

        $mockCarryforwardTagService = $this->createMock(CarryforwardTagServiceInterface::class);

        $mockCarryforwardTagService->expects($this->exactly(2))
            ->method('getTagsToCarryforward')
            ->willReturn($tags);

        $cachingCarryforwardTagService = new CachingCarryforwardTagService($mockCarryforwardTagService);

        $this->assertEquals(
            $tags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: [],
                    pullRequest: 1
                ),
                [new Tag('tag', 'commit', [10])]
            )
        );
        $this->assertEquals(
            $tags,
            $cachingCarryforwardTagService->getTagsToCarryforward(
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: [],
                    pullRequest: 1
                ),
                [new Tag('tag', 'commit', [10])]
            )
        );
    }
}
