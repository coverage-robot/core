<?php

namespace App\Tests\Service\Carryforward;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
use App\Query\Result\TagAvailabilityQueryResult;
use App\Query\TagAvailabilityQuery;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\History\CommitHistoryService;
use App\Service\QueryService;
use DateTimeImmutable;
use Packages\Models\Enum\JobState;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\JobStateChange;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CarryforwardTagServiceTest extends TestCase
{
    public function testNoTagsToCarryforward(): void
    {
        $mockCommitHistoryService = $this->createMock(CommitHistoryService::class);
        $mockCommitHistoryService->expects($this->never())
            ->method('getPrecedingCommits');
        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(
                TagAvailabilityQuery::class,
                self::callback(
                    function (QueryParameterBag $queryParameterBag) {
                        $this->assertEquals('mock-owner', $queryParameterBag->get(QueryParameter::OWNER));
                        $this->assertEquals('mock-repository', $queryParameterBag->get(QueryParameter::REPOSITORY));
                        return true;
                    }
                )
            )
            ->willReturn(
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult('tag-1', ['mock-commit']),
                        new TagAvailabilityQueryResult('tag-2', ['mock-commit-3', 'mock-commit-2']),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockCommitHistoryService,
            $mockQueryService,
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new JobStateChange(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-ref',
                'mock-commit',
                null,
                '',
                0,
                JobState::COMPLETED,
                JobState::COMPLETED,
                true,
                new DateTimeImmutable()
            ),
            [
                new Tag('tag-1', 'mock-commit'),
                new Tag('tag-2', 'mock-commit')
            ]
        );

        $this->assertEquals([], $carryforwardTags);
    }

    public function testTagToCarryforwardFromRecentCommit(): void
    {
        $mockCommitHistoryService = $this->createMock(CommitHistoryService::class);
        $mockCommitHistoryService->expects($this->once())
            ->method('getPrecedingCommits')
            ->willReturn(
                [
                    'mock-commit',
                    'mock-commit-2',
                    'mock-commit-3',
                ]
            );
        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(
                TagAvailabilityQuery::class,
                self::callback(
                    function (QueryParameterBag $queryParameterBag) {
                        $this->assertEquals('mock-owner', $queryParameterBag->get(QueryParameter::OWNER));
                        $this->assertEquals('mock-repository', $queryParameterBag->get(QueryParameter::REPOSITORY));
                        return true;
                    }
                )
            )
            ->willReturn(
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult('tag-1', ['mock-commit']),
                        new TagAvailabilityQueryResult('tag-2', ['mock-commit-3', 'mock-commit-2']),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockCommitHistoryService,
            $mockQueryService,
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new JobStateChange(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-ref',
                'mock-current-commit',
                null,
                '',
                0,
                JobState::COMPLETED,
                JobState::COMPLETED,
                true,
                new DateTimeImmutable()
            ),
            [
                new Tag('tag-1', 'mock-current-commit')
            ]
        );

        $this->assertEquals(
            [
                new Tag('tag-2', 'mock-commit-2')
            ],
            $carryforwardTags
        );
    }

    public function testTagsToCarryforwardFromMultiplePagesOfCommits(): void
    {
        $mockCommitHistoryService = $this->createMock(CommitHistoryService::class);
        $mockCommitHistoryService->expects($this->exactly(3))
            ->method('getPrecedingCommits')
            ->willReturnOnConsecutiveCalls(
                [
                    'mock-commit',
                    'mock-commit-2',
                    'mock-commit-3',
                ],
                [
                    'mock-commit-4',
                    'mock-commit-5',
                    'mock-commit-6',
                ],
                [
                    'mock-commit-7',
                    'mock-commit-8',
                    'mock-commit-9',
                ]
            );
        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(
                TagAvailabilityQuery::class,
                self::callback(
                    function (QueryParameterBag $queryParameterBag) {
                        $this->assertEquals('mock-owner', $queryParameterBag->get(QueryParameter::OWNER));
                        $this->assertEquals('mock-repository', $queryParameterBag->get(QueryParameter::REPOSITORY));
                        return true;
                    }
                )
            )
            ->willReturn(
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult('tag-1', ['mock-commit']),
                        new TagAvailabilityQueryResult('tag-2', ['mock-commit-8', 'mock-commit-11']),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockCommitHistoryService,
            $mockQueryService,
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new JobStateChange(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-ref',
                'mock-current-commit',
                null,
                '',
                0,
                JobState::COMPLETED,
                JobState::COMPLETED,
                true,
                new DateTimeImmutable()
            ),
            []
        );

        $this->assertEquals(
            [
                new Tag('tag-1', 'mock-commit'),
                new Tag('tag-2', 'mock-commit-8')
            ],
            $carryforwardTags
        );
    }

    public function testTagsToCarryforwardOutOfRangeOfCommits(): void
    {
        $mockCommitHistoryService = $this->createMock(CommitHistoryService::class);
        $mockCommitHistoryService->expects($this->exactly(5))
            ->method('getPrecedingCommits')
            ->willReturnOnConsecutiveCalls(
                [
                    'mock-commit',
                    'mock-commit-2',
                    'mock-commit-3',
                ],
                [
                    'mock-commit-4',
                    'mock-commit-5',
                    'mock-commit-6',
                ],
                [
                    'mock-commit-7',
                    'mock-commit-8',
                    'mock-commit-9',
                ],
                [
                    'mock-commit-10',
                    'mock-commit-11',
                    'mock-commit-12',
                ],
                [
                    'mock-commit-13',
                    'mock-commit-14',
                    'mock-commit-15',
                ]
            );
        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(
                TagAvailabilityQuery::class,
                self::callback(
                    function (QueryParameterBag $queryParameterBag) {
                        $this->assertEquals('mock-owner', $queryParameterBag->get(QueryParameter::OWNER));
                        $this->assertEquals('mock-repository', $queryParameterBag->get(QueryParameter::REPOSITORY));
                        return true;
                    }
                )
            )
            ->willReturn(
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult('tag-1', ['mock-commit']),
                        new TagAvailabilityQueryResult('tag-2', ['mock-commit-1010', 'mock-commit-999']),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockCommitHistoryService,
            $mockQueryService,
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new JobStateChange(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-ref',
                'mock-current-commit',
                null,
                '',
                0,
                JobState::COMPLETED,
                JobState::COMPLETED,
                true,
                new DateTimeImmutable()
            ),
            []
        );

        $this->assertEquals(
            [
                new Tag('tag-1', 'mock-commit')
            ],
            $carryforwardTags
        );
    }
}
