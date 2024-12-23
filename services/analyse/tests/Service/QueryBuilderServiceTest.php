<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use App\Service\QueryBuilderService;
use App\Tests\Mock\Factory\MockQueryFactory;
use DateTimeImmutable;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class QueryBuilderServiceTest extends KernelTestCase
{
    use MatchesSnapshots;

    public function testBuildFormatsQuery(): void
    {
        $queryParameters = QueryParameterBag::fromWaypoint(
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: [],
                pullRequest: 6
            )
        )
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                [
                    new CarryforwardTag(
                        'mock-tag',
                        'mock-commit',
                        [100],
                        [new DateTimeImmutable()]
                    )
                ]
            );

        $queryBuilder = new QueryBuilderService(
            new SqlFormatter(
                new NullHighlighter()
            ),
            $this->createMock(Serializer::class),
            $this->createMock(ValidatorInterface::class)
        );

        $this->assertMatchesTextSnapshot(
            $queryBuilder->build(
                MockQueryFactory::createMock(
                    $this,
                    QueryInterface::class,
                    'SELECT * FROM `mock-table` WHERE commit = "mock-commit" AND provider = "github"',
                    $this->createMock(QueryResultInterface::class)
                ),
                $queryParameters
            )
        );
    }

    public function testBuildValidatesQueryParameters(): void
    {
        $queryParameters = new QueryParameterBag();
        $queryParameters->set(QueryParameter::PROVIDER, Provider::GITHUB);

        $queryBuilder = new QueryBuilderService(
            new SqlFormatter(
                new NullHighlighter()
            ),
            $this->createMock(Serializer::class),
            $this->getContainer()->get(ValidatorInterface::class)
        );

        $mockQuery = MockQueryFactory::createMock(
            $this,
            QueryInterface::class,
            '',
            $this->createMock(QueryResultInterface::class)
        );

        $mockQuery->expects($this->once())
            ->method('getQueryParameterConstraints')
            ->willReturn([
                QueryParameter::PROVIDER->value => new Assert\IdenticalTo(value: 'not-a-provider')
            ]);

        $this->expectException(QueryException::class);

        $queryBuilder->build(
            $mockQuery,
            $queryParameters
        );
    }

    #[DataProvider('hashProvider')]
    public function testHashingIsPredictableRegardlessOfArrayOrder(
        string $queryClass,
        array $parameters,
        string $expectedHash
    ): void {
        $parameterBag = new QueryParameterBag();
        foreach ($parameters as $parameter => $value) {
            $parameterBag->set(
                QueryParameter::from($parameter),
                $value
            );
        }

        $queryBuilder = new QueryBuilderService(
            new SqlFormatter(
                new NullHighlighter()
            ),
            $this->getContainer()->get(SerializerInterface::class),
            $this->createMock(ValidatorInterface::class)
        );

        $hash = $queryBuilder->hash($queryClass, $parameterBag);

        $this->assertEquals($expectedHash, $hash);
    }

    public static function hashProvider(): array
    {
        return [
            [
                'some-class',
                [
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::REPOSITORY->value => 'some-value-2',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::LIMIT->value => 'some-value-4'
                ],
                'c8ad0013c06e2553ae33233c1e5a2179'
            ],
            [
                'some-class',
                [
                    QueryParameter::LIMIT->value => 'some-value-4',
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::REPOSITORY->value => 'some-value-2'
                ],
                'c8ad0013c06e2553ae33233c1e5a2179'
            ],
            [
                'some-class',
                [
                    QueryParameter::LIMIT->value => 'some-value-4',
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::COMMIT->value => ['commit-1', 'commit-2', 'commit-3']
                ],
                'bfaa256befa76c5cffa4e6f6273fa4df'
            ],
            [
                'some-class',
                [
                    QueryParameter::LIMIT->value => 'some-value-4',
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::COMMIT->value => ['commit-1', 'commit-3', 'commit-2']
                ],
                'bfaa256befa76c5cffa4e6f6273fa4df'
            ],
            [
                'some-class-2',
                [
                    QueryParameter::LIMIT->value => 'some-value-4',
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::COMMIT->value => ['commit-1', 'commit-3', 'commit-2']
                ],
                'be703085c2b224acf1fecc205c134239'
            ],
            [
                'some-class-3',
                [
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::CARRYFORWARD_TAGS->value => [
                        new Tag('tag-1', 'commit-1', [12]),
                        new Tag('tag-2', 'commit-1', [12]),
                        new Tag('tag-3', 'commit-3', [12]),
                    ]
                ],
                '107509417c5920a4e7f1636581f039b9'
            ],
            [
                'some-class-3',
                [
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::CARRYFORWARD_TAGS->value => [
                        new Tag('tag-1', 'commit-1', [12]),
                        new Tag('tag-3', 'commit-3', [12]),
                        new Tag('tag-2', 'commit-1', [12]),
                    ]
                ],
                '107509417c5920a4e7f1636581f039b9'
            ],
            [
                'some-class-4',
                [
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::LINES->value => [
                        'file-1' => [1, 2, 3],
                        'file-2' => [4, 5, 6]
                    ]
                ],
                'dab8bb5fa43a6477071e0106decec937'
            ],
            [
                'some-class-4',
                [
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::LINES->value => [
                        'file-1' => [2, 1, 3],
                        'file-2' => [4, 6, 5]
                    ]
                ],
                'dab8bb5fa43a6477071e0106decec937'
            ],
            [
                'some-class-4',
                [
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::LINES->value => [
                        'file-2' => [4, 6, 5],
                        'file-1' => [2, 1, 3],
                    ]
                ],
                'dab8bb5fa43a6477071e0106decec937'
            ],
        ];
    }
}
