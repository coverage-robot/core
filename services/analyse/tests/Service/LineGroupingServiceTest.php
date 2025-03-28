<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Query\Result\LineCoverageQueryResult;
use App\Query\Result\QueryResultInterface;
use App\Query\Result\QueryResultIterator;
use App\Service\LineGroupingService;
use ArrayIterator;
use DateTimeImmutable;
use Iterator;
use Packages\Contracts\Line\LineState;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\UploadsFinalised;
use Packages\Message\PublishableMessage\PublishableMissingCoverageLineCommentMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchLineCommentMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class LineGroupingServiceTest extends TestCase
{
    #[DataProvider('diffDataProvider')]
    public function testGroupingAnnotationsAgainstDiff(
        EventInterface $event,
        DateTimeImmutable $validUntil,
        array $diff,
        QueryResultIterator $lineCoverage,
        array $expectedAnnotations
    ): void {
        $groupingService = new LineGroupingService(
            new NullLogger()
        );

        $this->assertEquals(
            $expectedAnnotations,
            $groupingService->generateComments(
                $event,
                $diff,
                $lineCoverage,
                $validUntil
            )
        );
    }

    public static function diffDataProvider(): Iterator
    {
        $date = new DateTimeImmutable();

        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
            projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: '',
            commit: '',
            parent: ['mock-parent-1'],
            pullRequest: 1,
            baseCommit: 'mock-base-commit',
            baseRef: 'mock-base-ref',
            eventTime: $date
        );
        yield 'Two fully uncovered method' => [
            $event,
            $date,
            [
                'mock-file' => range(1, 16)
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file',
                        1,
                        LineState::UNCOVERED,
                        true,
                        false,
                        false,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        2,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        3,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        6,
                        LineState::UNCOVERED,
                        true,
                        false,
                        false,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        7,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        8,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                ]),
                6,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    true,
                    1,
                    3,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    true,
                    6,
                    8,
                    $date
                ),
            ]
        ];

        yield 'Statements modified inside method' => [
            $event,
            $date,
            [
                'mock-file' => [
                    3,
                    4,
                    7,
                    8,
                    9
                ]
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file',
                        3,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        4,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        7,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        8,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        9,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                ]),
                5,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    false,
                    3,
                    4,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    false,
                    7,
                    9,
                    $date
                ),
            ]
        ];

        yield 'New method and modified method' => [
            $event,
            $date,
            [
                'mock-file' => [
                    3,
                    4,
                    7,
                    8,
                    9
                ]
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file',
                        3,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        4,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        7,
                        LineState::UNCOVERED,
                        true,
                        false,
                        false,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        8,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        9,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                ]),
                5,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    false,
                    3,
                    4,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    true,
                    7,
                    9,
                    $date
                ),
            ]
        ];

        yield 'Modified method with partial branch' => [
            $event,
            $date,
            [
                'mock-file' => range(3, 10)
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file',
                        3,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        4,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        5,
                        LineState::PARTIAL,
                        false,
                        true,
                        false,
                        2,
                        1
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        6,
                        LineState::COVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        7,
                        LineState::COVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        9,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        10,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                ]),
                7,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishablePartialBranchLineCommentMessage(
                    $event,
                    'mock-file',
                    5,
                    5,
                    2,
                    1,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    false,
                    3,
                    5,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    false,
                    9,
                    10,
                    $date
                ),
            ]
        ];

        yield 'Completely uncovered branch' => [
            $event,
            $date,
            [
                'mock-file' => range(5, 10)
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file',
                        5,
                        LineState::PARTIAL,
                        false,
                        true,
                        false,
                        2,
                        0
                    ),
                ]),
                1,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishablePartialBranchLineCommentMessage(
                    $event,
                    'mock-file',
                    5,
                    5,
                    2,
                    0,
                    $date
                ),
            ]
        ];

        yield 'Completely uncovered branch and overlapping uncovered statements' => [
            $event,
            $date,
            [
                'mock-file' => range(1, 8)
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file',
                        1,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        5,
                        LineState::PARTIAL,
                        false,
                        true,
                        false,
                        2,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        7,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        8,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                ]),
                4,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishablePartialBranchLineCommentMessage(
                    $event,
                    'mock-file',
                    5,
                    5,
                    2,
                    0,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    false,
                    1,
                    8,
                    $date
                ),
            ]
        ];

        yield 'Multiple files' => [
            $event,
            $date,
            [
                'mock-file-1' => range(1, 2),
                'mock-file-2' => range(10, 12)
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        1,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        2,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-2',
                        10,
                        LineState::COVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-2',
                        11,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-2',
                        12,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                ]),
                5,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file-1',
                    false,
                    1,
                    2,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file-2',
                    false,
                    11,
                    12,
                    $date
                )
            ]
        ];

        yield 'Uncovered blocks split by covered blocks' => [
            $event,
            $date,
            [
                'mock-file-1' => range(1, 10),
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        1,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        2,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        3,
                        LineState::COVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        4,
                        LineState::COVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        5,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        6,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                ]),
                6,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file-1',
                    false,
                    1,
                    2,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file-1',
                    false,
                    5,
                    6,
                    $date
                )
            ]
        ];

        yield 'Method signature change only' => [
            $event,
            $date,
            [
                'mock-file-1' => [
                    5,
                    10,
                    11
                ]
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        5,
                        LineState::UNCOVERED,
                        true,
                        false,
                        false,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        10,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file-1',
                        11,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                ]),
                3,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file-1',
                    true,
                    5,
                    5,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file-1',
                    false,
                    10,
                    11,
                    $date
                ),
            ]
        ];

        yield 'Bridging uncovered diff with empty lines' => [
            $event,
            $date,
            [
                'mock-file' => range(1, 11)
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file',
                        1,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    // Theres 6 uncoverable lines - perhaps empty lines, or a long code comment
                    new LineCoverageQueryResult(
                        'mock-file',
                        7,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        8,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        9,
                        LineState::COVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        10,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                ]),
                5,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    false,
                    1,
                    8,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    false,
                    10,
                    10,
                    $date
                ),
            ]
        ];

        yield 'Method signature changed as last line of diff' => [
            $event,
            $date,
            [
                'mock-file' => range(10, 11)
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file',
                        11,
                        LineState::UNCOVERED,
                        true,
                        false,
                        false,
                        0,
                        0
                    ),
                ]),
                1,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    true,
                    11,
                    11,
                    $date
                ),
            ]
        ];

        yield 'Block starting with uncoverable lines' => [
            $event,
            $date,
            [
                'mock-file' => [
                    185,
                    240,
                    241,
                    242
                ]
            ],
            new QueryResultIterator(
                new ArrayIterator([
                    new LineCoverageQueryResult(
                        'mock-file',
                        185,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        241,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                    new LineCoverageQueryResult(
                        'mock-file',
                        242,
                        LineState::UNCOVERED,
                        false,
                        false,
                        true,
                        0,
                        0
                    ),
                ]),
                3,
                static fn(QueryResultInterface $result): QueryResultInterface => $result
            ),
            [
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    false,
                    185,
                    185,
                    $date
                ),
                new PublishableMissingCoverageLineCommentMessage(
                    $event,
                    'mock-file',
                    false,
                    241,
                    242,
                    $date
                ),
            ]
        ];
    }
}
