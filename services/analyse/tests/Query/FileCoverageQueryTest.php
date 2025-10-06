<?php

declare(strict_types=1);

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\FileCoverageQuery;
use ArrayIterator;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Provider\Provider;

final class FileCoverageQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): string
    {
        return FileCoverageQuery::class;
    }

    /**
     * @return QueryParameterBag[]
     */
    #[Override]
    public static function getQueryParameters(): array
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: '0193f0c7-c37f-7d9e-92b4-b06f00e2296a',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
            history: [],
            diff: [],
            pullRequest: 12
        );

        $lineScopedParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(QueryParameter::LIMIT, 50)
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
            ->set(QueryParameter::LIMIT, 10)
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
                    new CarryforwardTag(
                        '4',
                        'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                        [1],
                        [new DateTimeImmutable('2024-01-01 02:00:00')]
                    )
                ]
            );

        $carryforwardAndUploadsParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(QueryParameter::LIMIT, 20)
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
                    new CarryforwardTag(
                        '4',
                        'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                        [1],
                        [new DateTimeImmutable('2024-01-01 02:00:00')]
                    )
                ]
            );

        $carryforwardAndUploadsParametersWithNoLimit = QueryParameterBag::fromWaypoint($waypoint)
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
                    new CarryforwardTag(
                        '4',
                        'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                        [1],
                        [new DateTimeImmutable('2024-01-01 02:00:00')]
                    )
                ]
            );

        return [
            $lineScopedParameters,
            $carryforwardParameters,
            $carryforwardAndUploadsParameters,
            $carryforwardAndUploadsParametersWithNoLimit
        ];
    }

    /**
     * @return ArrayIterator[]
     */
    #[Override]
    public static function getQueryResults(): array
    {
        return [
            new ArrayIterator([
                [
                    'fileName' => 'mock-file',
                    'lines' => [1],
                    'coveredLines' => [1],
                    'partialLines' => [],
                    'uncoveredLines' => [],
                    'coveragePercentage' => 100.0
                ],
            ]),
            new ArrayIterator([
                [
                    'fileName' => 'mock-file',
                    'lines' => [2],
                    'coveredLines' => [2],
                    'partialLines' => [],
                    'uncoveredLines' => [],
                    'coveragePercentage' => 100.0
                ],
                [
                    'fileName' => 'mock-file-2',
                    'lines' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                    'coveredLines' => [1, 4, 5, 6, 7],
                    'partialLines' => [],
                    'uncoveredLines' => [2, 3, 8, 9, 10],
                    'coveragePercentage' => 50.0
                ]
            ])
        ];
    }
}
