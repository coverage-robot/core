<?php

namespace App\Tests\Service;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\CoverageQueryResult;
use App\Query\TotalCoverageQuery;
use App\Service\QueryBuilderService;
use App\Tests\Mock\Factory\MockQueryFactory;
use DateTimeImmutable;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class QueryBuilderServiceTest extends KernelTestCase
{
    public function testBuildFormatsQuery(): void
    {
        $queryParameters = QueryParameterBag::fromEvent(
            new Upload(
                'mock-uploadId',
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-commit',
                [],
                'main',
                'project-root',
                12,
                new Tag('mock-tag', 'mock-commit'),
                new DateTimeImmutable('2023-09-02T10:12:00+00:00'),
            )
        );

        $queryBuilder = new QueryBuilderService(
            new SqlFormatter(
                new NullHighlighter()
            ),
            $this->createMock(SerializerInterface::class)
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
            $this->createMock(SerializerInterface::class)
        );

        $this->expectException(QueryException::class);

        $queryBuilder->build(
            MockQueryFactory::createMock(
                $this,
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
                "a56cb60da1052c5e33a256cebe6d172b"
            ],
            [
                'some-class',
                [
                    QueryParameter::LIMIT->value => 'some-value-4',
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::REPOSITORY->value => 'some-value-2'
                ],
                "a56cb60da1052c5e33a256cebe6d172b"
            ],
            [
                'some-class',
                [
                    QueryParameter::LIMIT->value => 'some-value-4',
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::COMMIT->value => ['commit-1', 'commit-2', 'commit-3']
                ],
                "3748fe079457569316accc996d97001a"
            ],
            [
                'some-class',
                [
                    QueryParameter::LIMIT->value => 'some-value-4',
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::COMMIT->value => ['commit-1', 'commit-3', 'commit-2']
                ],
                "3748fe079457569316accc996d97001a"
            ],
            [
                'some-class-2',
                [
                    QueryParameter::LIMIT->value => 'some-value-4',
                    QueryParameter::OWNER->value => 'some-value-1',
                    QueryParameter::PROVIDER->value => 'some-value-3',
                    QueryParameter::COMMIT->value => ['commit-1', 'commit-3', 'commit-2']
                ],
                "e09b365192ef2fd9b95ee526979c4fa6"
            ]
        ];
    }
}
