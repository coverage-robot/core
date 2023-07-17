<?php

namespace App\Tests\Service\Carryforward;

use App\Query\Result\CommitCollectionQueryResult;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\History\CommitHistoryService;
use App\Service\QueryService;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CarryforwardTagServiceTest extends TestCase
{
    public function testCarryingForwardTagsFromPreviousCommits(): void
    {
        $mockCommitHistoryService = $this->createMock(CommitHistoryService::class);
        $mockQueryService = $this->createMock(QueryService::class);

        $carryforwardTagService = new CarryforwardTagService(
            $mockCommitHistoryService,
            $mockQueryService,
            new NullLogger()
        );

        $upload = Upload::from(
            [
                'uploadId' => 'mock-uuid',
                'provider' => Provider::GITHUB->value,
                'commit' => 'mock-commit',
                'parent' => ["mock-parent"],
                'ref' => "mock-ref",
                'owner' => "owner",
                'repository' => "repository",
                'tag' => "tag-4"
            ]
        );

        $mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                CommitCollectionQueryResult::from([
                    [
                        'commit' => 'mock-commit-1',
                        'tags' => ['tag-4']
                    ],
                ]),
                CommitCollectionQueryResult::from([
                    [
                        'commit' => 'mock-commit-2',
                        'tags' => ['tag-1', 'tag-2']
                    ],
                    [
                        'commit' => 'mock-commit-3',
                        'tags' => [ 'tag-2']
                    ],
                    [
                        'commit' => 'mock-commit-4',
                        'tags' => [ 'tag-3']
                    ]
                ])
            );

        $tags = $carryforwardTagService->getTagsToCarryforward($upload);

        $this->assertEquals(
            [
                new Tag('tag-1', 'mock-commit-2'),
                new Tag('tag-2', 'mock-commit-2'),
                new Tag('tag-3', 'mock-commit-4')
            ],
            $tags
        );
    }

    public function testNoTagsNeedCarryingForward(): void
    {
        $mockCommitHistoryService = $this->createMock(CommitHistoryService::class);
        $mockQueryService = $this->createMock(QueryService::class);

        $carryforwardTagService = new CarryforwardTagService(
            $mockCommitHistoryService,
            $mockQueryService,
            new NullLogger()
        );

        $upload = Upload::from(
            [
                'uploadId' => 'mock-uuid',
                'provider' => Provider::GITHUB->value,
                'commit' => 'mock-commit',
                'parent' => ["mock-parent"],
                'ref' => "mock-ref",
                'owner' => "owner",
                'repository' => "repository",
                'tag' => "tag-4"
            ]
        );

        $mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                CommitCollectionQueryResult::from([
                    [
                        'commit' => 'mock-commit-1',
                        'tags' => ['tag-4', 'tag-2']
                    ],
                ]),
                CommitCollectionQueryResult::from([
                    [
                        'commit' => 'mock-commit-2',
                        'tags' => ['tag-4', 'tag-2']
                    ],
                    [
                        'commit' => 'mock-commit-3',
                        'tags' => ['tag-4', 'tag-2']
                    ],
                    [
                        'commit' => 'mock-commit-4',
                        'tags' => ['tag-4', 'tag-2']
                    ],
                ])
            );

        $tags = $carryforwardTagService->getTagsToCarryforward($upload);

        $this->assertEquals(
            [],
            $tags
        );
    }
}
