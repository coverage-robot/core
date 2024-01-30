<?php

namespace App\Tests\Service\Diff;

use App\Model\ReportWaypoint;
use App\Service\Diff\CachingDiffParserService;
use App\Service\Diff\DiffParserServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;

final class CachingDiffParserServiceTest extends TestCase
{
    public function testCachesRepeatedRequests(): void
    {
        $mockDiffParser = $this->createMock(DiffParserServiceInterface::class);
        $mockDiffParser->expects($this->once())
            ->method('get')
            ->willReturn([]);

        $cachingDiffParser = new CachingDiffParserService($mockDiffParser);

        $mockWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'owner',
            repository: 'repository',
            ref: 'ref',
            commit: 'commit',
            history: [],
            diff: [],
            pullRequest: 12
        );

        $this->assertEquals([], $cachingDiffParser->get($mockWaypoint));
        $this->assertEquals([], $cachingDiffParser->get($mockWaypoint));
    }

    public function testDoesNotCacheDifferentUploads(): void
    {
        $mockDiffParser = $this->createMock(DiffParserServiceInterface::class);

        $mockDiffParser->expects($this->exactly(2))
            ->method('get')
            ->willReturn([]);

        $cachingDiffParser = new CachingDiffParserService($mockDiffParser);

        $this->assertEquals(
            [],
            $cachingDiffParser->get(
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref-2',
                    commit: 'mock-commit-2',
                    history: [],
                    diff: [],
                    pullRequest: 2
                )
            )
        );
        $this->assertEquals(
            [],
            $cachingDiffParser->get(
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: [],
                    pullRequest: 1
                )
            )
        );
    }
}
