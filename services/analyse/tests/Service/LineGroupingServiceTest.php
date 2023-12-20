<?php

namespace App\Tests\Service;

use App\Query\Result\LineCoverageQueryResult;
use App\Service\LineGroupingService;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\UploadsFinalised;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchAnnotationMessage;
use Packages\Models\Enum\LineState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LineGroupingServiceTest extends TestCase
{
    #[DataProvider('diffDataProvider')]
    public function testGroupingAnnotationsAgainstDiff(
        EventInterface $event,
        DateTimeImmutable $validUntil,
        array $diff,
        array $lineCoverage,
        array $expectedAnnotations
    ): void {
        $groupingService = new LineGroupingService(
            new NullLogger()
        );

        $this->assertEquals(
            $expectedAnnotations,
            $groupingService->generateAnnotations(
                $event,
                $diff,
                $lineCoverage,
                $validUntil
            )
        );
    }

    public static function diffDataProvider(): array
    {
        $date = new DateTimeImmutable();

        $event = new UploadsFinalised(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            '',
            '',
            ['mock-parent-1'],
            1,
            'mock-base-commit',
            'mock-base-ref',
            $date
        );

        return [
            'Two fully uncovered method' => [
                $event,
                $date,
                [
                    'mock-file' => range(1, 16)
                ],
                [
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
                ],
                [
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        true,
                        1,
                        3,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        true,
                        6,
                        8,
                        $date
                    ),
                ]
            ],
            'Statements modified inside method' => [
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
                [
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
                ],
                [
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        false,
                        3,
                        4,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        false,
                        7,
                        9,
                        $date
                    ),
                ]
            ],
            'New method and modified method' => [
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
                [
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
                ],
                [
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        false,
                        3,
                        4,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        true,
                        7,
                        9,
                        $date
                    ),
                ]
            ],
            'Modified method with partial branch' => [
                $event,
                $date,
                [
                    'mock-file' => range(3, 10)
                ],
                [
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
                ],
                [
                    new PublishablePartialBranchAnnotationMessage(
                        $event,
                        'mock-file',
                        5,
                        5,
                        2,
                        1,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        false,
                        3,
                        5,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        false,
                        9,
                        10,
                        $date
                    ),
                ]
            ],
            'Completely uncovered branch' => [
                $event,
                $date,
                [
                    'mock-file' => range(5, 10)
                ],
                [
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
                ],
                [
                    new PublishablePartialBranchAnnotationMessage(
                        $event,
                        'mock-file',
                        5,
                        5,
                        2,
                        0,
                        $date
                    ),
                ]
            ],
            'Completely uncovered branch and overlapping uncovered statements' => [
                $event,
                $date,
                [
                    'mock-file' => range(1, 8)
                ],
                [
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
                ],
                [
                    new PublishablePartialBranchAnnotationMessage(
                        $event,
                        'mock-file',
                        5,
                        5,
                        2,
                        0,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        false,
                        1,
                        8,
                        $date
                    ),
                ]
            ],
            'Multiple files' => [
                $event,
                $date,
                [
                    'mock-file-1' => range(1, 2),
                    'mock-file-2' => range(10, 12)
                ],
                [
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
                ],
                [
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file-1',
                        false,
                        1,
                        2,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file-2',
                        false,
                        11,
                        12,
                        $date
                    )
                ]
            ],
            'Uncovered blocks split by covered blocks' => [
                $event,
                $date,
                [
                    'mock-file-1' => range(1, 10),
                ],
                [
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
                ],
                [
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file-1',
                        false,
                        1,
                        2,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file-1',
                        false,
                        5,
                        6,
                        $date
                    )
                ]
            ],
            'Method signature change only' => [
                $event,
                $date,
                [
                    'mock-file-1' => [
                        5,
                        10,
                        11
                    ]
                ],
                [
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
                ],
                [
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file-1',
                        true,
                        5,
                        5,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file-1',
                        false,
                        10,
                        11,
                        $date
                    ),
                ]
            ],
            'Bridging uncovered diff with empty lines' => [
                $event,
                $date,
                [
                    'mock-file' => range(1, 11)
                ],
                [
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
                ],
                [
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        false,
                        1,
                        8,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        false,
                        10,
                        10,
                        $date
                    ),
                ]
            ],
            'Method signature changed as last line of diff' => [
                $event,
                $date,
                [
                    'mock-file' => range(10, 11)
                ],
                [
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
                ],
                [
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        true,
                        11,
                        11,
                        $date
                    ),
                ]
            ],
            'Block starting with uncoverable lines' => [
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
                [
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
                ],
                [
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        false,
                        185,
                        185,
                        $date
                    ),
                    new PublishableMissingCoverageAnnotationMessage(
                        $event,
                        'mock-file',
                        false,
                        241,
                        242,
                        $date
                    ),
                ]
            ],
        ];
    }
}
