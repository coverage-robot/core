<?php

namespace App\Tests\Service\Carryforward;

use App\Query\Result\CommitCollectionQueryResult;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\History\CommitHistoryService;
use App\Service\QueryService;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CarryforwardTagServiceTest extends TestCase
{
    public function testCarryingForwardTagsFromPreviousCommits(): void
    {
        $mockCommitHistoryService = $this->createMock(CommitHistoryService::class);
        $mockQueryService = $this->createMock(QueryService::class);

        $mockCommitHistoryService->expects($this->once())
            ->method('getPrecedingCommits')
            ->willReturn([
                'mock-commit-2',
                'mock-commit-3',
                'mock-commit-4',
                'mock-commit-5',
                'mock-commit-6',
                'mock-commit-7'
            ]);

        $carryforwardTagService = new CarryforwardTagService(
            $mockCommitHistoryService,
            $mockQueryService,
            new NullLogger()
        );

        $upload = new Upload(
            'mock-uuid',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit-1',
            ['mock-parent-commit'],
            'mock-ref',
            'mock-project-root',
            null,
            new Tag('mock-tag', 'mock-commit-1')
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
                        'commit' => 'mock-commit-4',
                        'tags' => ['tag-2']
                    ],
                    [
                        'commit' => 'mock-commit-5',
                        'tags' => ['tag-4']
                    ],
                    [
                        'commit' => 'mock-commit-7',
                        'tags' => ['tag-3']
                    ]
                ])
            );

        $tags = $carryforwardTagService->getTagsToCarryforward($upload);

        $this->assertEquals(
            [
                new Tag('tag-1', 'mock-commit-2'),
                new Tag('tag-2', 'mock-commit-2'),
                new Tag('tag-3', 'mock-commit-7')
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

        $upload = new Upload(
            'mock-uuid',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit-1',
            ['mock-parent-commit'],
            'mock-ref',
            'mock-project-root',
            null,
            new Tag('mock-tag', 'mock-commit-1')
        );

        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                CommitCollectionQueryResult::from([
                    [
                        'commit' => 'mock-commit-1',
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

    public function testCarryingForwardWithNoCurrentTags(): void
    {
        $mockCommitHistoryService = $this->createMock(CommitHistoryService::class);
        $mockQueryService = $this->createMock(QueryService::class);

        $mockCommitHistoryService->expects($this->once())
            ->method('getPrecedingCommits')
            ->willReturn([
                'mock-commit-2',
                'mock-commit-3',
                'mock-commit-4',
                'mock-commit-5',
                'mock-commit-6',
                'mock-commit-7'
            ]);

        $carryforwardTagService = new CarryforwardTagService(
            $mockCommitHistoryService,
            $mockQueryService,
            new NullLogger()
        );

        $upload = new Upload(
            'mock-uuid',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit-1',
            ['mock-parent-commit'],
            'mock-ref',
            'mock-project-root',
            null,
            new Tag('mock-tag', 'mock-commit-1')
        );

        // This shouldn't really happen (no current tags), as the upload we're analysing currently
        // should _always_ be in BigQuery, but we should handle it gracefully if something goes wrong
        // during BigQuery persistence.
        $mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                CommitCollectionQueryResult::from([]),
                CommitCollectionQueryResult::from([
                    [
                        'commit' => 'mock-commit-2',
                        'tags' => ['tag-1', 'tag-2']
                    ],
                    [
                        'commit' => 'mock-commit-4',
                        'tags' => ['tag-2']
                    ],
                    [
                        'commit' => 'mock-commit-5',
                        'tags' => ['tag-4']
                    ],
                    [
                        'commit' => 'mock-commit-7',
                        'tags' => ['tag-3']
                    ]
                ])
            );

        $tags = $carryforwardTagService->getTagsToCarryforward($upload);

        $this->assertEquals(
            [
                new Tag('tag-1', 'mock-commit-2'),
                new Tag('tag-2', 'mock-commit-2'),
                new Tag('tag-4', 'mock-commit-5'),
                new Tag('tag-3', 'mock-commit-7')
            ],
            $tags
        );
    }
}
