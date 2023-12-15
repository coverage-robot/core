<?php

namespace App\Tests\Service;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\Result\CoverageQueryResult;
use App\Query\TotalCoverageQuery;
use App\Service\QueryBuilderService;
use App\Tests\Mock\Factory\MockQueryFactory;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Packages\Contracts\Provider\Provider;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class QueryBuilderServiceTest extends KernelTestCase
{
    public function testBuildFormatsQuery(): void
    {
        $queryParameters = QueryParameterBag::fromWaypoint(
            new ReportWaypoint(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-ref',
                'mock-commit'
            )
        );

        $queryBuilder = new QueryBuilderService(
            new SqlFormatter(
                new NullHighlighter()
            ),
            $this->createMock(Serializer::class)
        );

        $this->assertEquals(
            <<<SQL
            SELECT
              *
            FROM
              `mock-table`
            WHERE
              commit = "mock-commit"
              AND provider = "github"
            SQL,
            $queryBuilder->build(
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    TotalCoverageQuery::class,
                    'SELECT * FROM `mock-table` WHERE commit = "mock-commit" AND provider = "github"',
                    $this->createMock(CoverageQueryResult::class)
                ),
                'mock-table',
                $queryParameters
            )
        );
    }

    public function testBuildValidatesQueryParameters(): void
    {
        $queryParameters = new QueryParameterBag();

        $queryBuilder = new QueryBuilderService(
            new SqlFormatter(
                new NullHighlighter()
            ),
            $this->createMock(Serializer::class)
        );

        $this->expectException(QueryException::class);

        $queryBuilder->build(
            MockQueryFactory::createMock(
                $this,
                $this->getContainer(),
                TotalCoverageQuery::class,
                '',
                $this->createMock(CoverageQueryResult::class)
            ),
            'mock-table',
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
            $this->getContainer()->get(SerializerInterface::class)
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
                        new Tag('tag-1', 'commit-1'),
                        new Tag('tag-2', 'commit-1'),
                        new Tag('tag-3', 'commit-3'),
                    ]
                ],
                'e1d213850cef9ac67bccd8ab1b674a98'
            ],
            [
                'some-class-3',
                [
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::CARRYFORWARD_TAGS->value => [
                        new Tag('tag-1', 'commit-1'),
                        new Tag('tag-3', 'commit-3'),
                        new Tag('tag-2', 'commit-1'),
                    ]
                ],
                'e1d213850cef9ac67bccd8ab1b674a98'
            ],
            [
                'some-class-4',
                [
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::LINE_SCOPE->value => [
                        'file-1' => [1, 2, 3],
                        'file-2' => [4, 5, 6]
                    ]
                ],
                'e2392863844d4213424f6e2f7ab9db10'
            ],
            [
                'some-class-4',
                [
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::LINE_SCOPE->value => [
                        'file-1' => [2, 1, 3],
                        'file-2' => [4, 6, 5]
                    ]
                ],
                'e2392863844d4213424f6e2f7ab9db10'
            ],
            [
                'some-class-4',
                [
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::LINE_SCOPE->value => [
                        'file-2' => [4, 6, 5],
                        'file-1' => [2, 1, 3],
                    ]
                ],
                'e2392863844d4213424f6e2f7ab9db10'
            ],
        ];
    }
}
