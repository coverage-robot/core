<?php

declare(strict_types=1);

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\LineCoverageQuery;
use ArrayIterator;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Line\LineState;
use Packages\Contracts\Provider\Provider;

final class LineCoverageQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): string
    {
        return LineCoverageQuery::class;
    }

    #[Override]
    public static function getQueryParameters(): array
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: '0193f0cd-ad49-7e14-b6d2-e88545efc889',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
            history: [],
            diff: []
        );

        $scopedParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::UPLOADS,
                ['0193f0c5-bae7-7b67-bb26-81e781146de8', '0193f0c5-d84f-7470-a008-97c2b9538933']
            )
            ->set(QueryParameter::INGEST_PARTITIONS, [
                new DateTimeImmutable('2024-01-03 00:00:00'),
                new DateTimeImmutable('2024-01-03 00:00:00')
            ])
            ->set(
                QueryParameter::LINES,
                [
                    'mock-file' => [1, 2, 3],
                    'mock-file-2' => [10, 11, 12]
                ]
            );

        $carryforwardParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(QueryParameter::UPLOADS, [])
            ->set(QueryParameter::INGEST_PARTITIONS, [])
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                [
                    new CarryforwardTag(
                        '1',
                        'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        [1],
                        [new DateTimeImmutable('2024-01-03 00:00:00')]
                    ),
                    new CarryforwardTag(
                        '2',
                        'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        [1],
                        [new DateTimeImmutable('2024-01-03 00:00:00')]
                    ),
                    new CarryforwardTag(
                        '3',
                        'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                        [1],
                        [new DateTimeImmutable('2024-01-01 02:00:00')]
                    ),
                    new CarryforwardTag('4', 'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a', [1], [
                        new DateTimeImmutable(
                            '2024-01-01 02:00:00'
                        )
                    ])
                ]
            );

        $carryforwardAndUploadsParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::UPLOADS,
                ['0193f0c5-bae7-7b67-bb26-81e781146de8', '0193f0c5-d84f-7470-a008-97c2b9538933']
            )
            ->set(QueryParameter::INGEST_PARTITIONS, [
                new DateTimeImmutable('2024-01-03 00:00:00'),
                new DateTimeImmutable('2024-01-03 00:00:00')
            ])
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                [
                    new CarryforwardTag(
                        '1',
                        'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        [1],
                        [new DateTimeImmutable('2024-01-03 00:00:00')]
                    ),
                    new CarryforwardTag(
                        '2',
                        'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        [1],
                        [new DateTimeImmutable('2024-01-03 00:00:00')]
                    ),
                    new CarryforwardTag(
                        '3',
                        'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                        [1],
                        [new DateTimeImmutable('2024-01-01 02:00:00')]
                    ),
                    new CarryforwardTag('4', 'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a', [1], [
                        new DateTimeImmutable(
                            '2024-01-01 02:00:00'
                        )
                    ])
                ]
            );

        return [
            $scopedParameters,
            $carryforwardParameters,
            $carryforwardAndUploadsParameters
        ];
    }

    #[Override]
    public static function getQueryResults(): array
    {
        return [
            new ArrayIterator([
                [
                    'fileName' => 'mock-file',
                    'lineNumber' => 1,
                    'state' => LineState::COVERED->value,
                    'containsMethod' => false,
                    'containsBranch' => false,
                    'containsStatement' => true,
                    'totalBranches' => 0,
                    'coveredBranches' => 0,
                ],
            ]),
            new ArrayIterator([
                [
                    'fileName' => 'mock-file',
                    'lineNumber' => 1,
                    'state' => LineState::COVERED->value,
                    'containsMethod' => false,
                    'containsBranch' => false,
                    'containsStatement' => true,
                    'totalBranches' => 0,
                    'coveredBranches' => 0,
                ],
                [
                    'fileName' => 'mock-file-2',
                    'lineNumber' => 2,
                    'state' => LineState::UNCOVERED->value,
                    'containsMethod' => false,
                    'containsBranch' => false,
                    'containsStatement' => true,
                    'totalBranches' => 0,
                    'coveredBranches' => 0,
                ],
                [
                    'fileName' => 'mock-file-3',
                    'lineNumber' => 3,
                    'state' => LineState::PARTIAL->value,
                    'containsMethod' => false,
                    'containsBranch' => false,
                    'containsStatement' => true,
                    'totalBranches' => 0,
                    'coveredBranches' => 0,
                ],
            ])
        ];
    }
}
