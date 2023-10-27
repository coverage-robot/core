<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\CommitSuccessfulTagsQuery;
use App\Query\QueryInterface;
use App\Query\Result\CommitCollectionQueryResult;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\QueryResults;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

class CommitSuccessfulTagsQueryTest extends AbstractQueryTestCase
{
    public function getQueryClass(): QueryInterface
    {
        return new CommitSuccessfulTagsQuery(
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::BIGQUERY_UPLOAD_TABLE->value => 'mock-table'
                ]
            )
        );
    }

    /**
     * @inheritDoc
     */
    public static function getExpectedQueries(): array
    {
        return [
            <<<SQL
            SELECT
              commit,
              ARRAY_AGG(
                STRUCT(tag as name, commit as commit)
              ) as tags,
            FROM
              `mock-table`
            WHERE
              commit = "mock-commit"
              AND repository = "mock-repository"
              AND owner = "mock-owner"
              AND provider = "github"
            GROUP BY
              commit
            SQL,
            <<<SQL
            SELECT
              commit,
              ARRAY_AGG(
                STRUCT(tag as name, commit as commit)
              ) as tags,
            FROM
              `mock-table`
            WHERE
              commit IN ("mock-commit", "mock-commit-2")
              AND repository = "mock-repository"
              AND owner = "mock-owner"
              AND provider = "github"
            GROUP BY
              commit
            SQL,
        ];
    }

    public static function getQueryParameters(): array
    {
        $upload = new Upload(
            'mock-uuid',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            ['mock-parent-commit'],
            'mock-ref',
            'mock-project-root',
            null,
            new Tag('mock-tag', 'mock-commit-1')
        );

        $multipleCommitParameters = QueryParameterBag::fromEvent($upload);
        $multipleCommitParameters->set(
            QueryParameter::COMMIT,
            ['mock-commit', 'mock-commit-2']
        );

        return [
            ...parent::getQueryParameters(),
            $multipleCommitParameters
        ];
    }

    #[DataProvider('resultsDataProvider')]
    public function testParseResults(array $queryResult): void
    {
        $mockBigQueryResult = $this->createMock(QueryResults::class);
        $mockBigQueryResult->expects($this->once())
            ->method('isComplete')
            ->willReturn(true);
        $mockBigQueryResult->expects($this->once())
            ->method('rows')
            ->willReturn($queryResult);

        $result = $this->getQueryClass()
            ->parseResults($mockBigQueryResult);

        $this->assertInstanceOf(CommitCollectionQueryResult::class, $result);
    }

    #[DataProvider('parametersDataProvider')]
    public function testValidateParameters(QueryParameterBag $parameters, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(QueryException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        $this->getQueryClass()->validateParameters($parameters);
    }

    public static function resultsDataProvider(): array
    {
        return [
            [
                [
                    [
                        'commit' => 'mock-commit',
                        'tags' => [
                            [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit'
                            ]
                        ]
                    ]
                ],
            ],
            [
                [
                    [
                        'commit' => 'mock-commit',
                        'tags' => [
                            [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit'
                            ]
                        ]
                    ],
                    [
                        'commit' => 'mock-commit-2',
                        'tags' => [
                            [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit-2'
                            ],
                            [
                                'name' => 'mock-tag-2',
                                'commit' => 'mock-commit-2'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function parametersDataProvider(): array
    {
        return [
            [
                new QueryParameterBag(),
                false
            ],
            [
                QueryParameterBag::fromEvent(
                    new Upload(
                        'mock-uuid',
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-commit',
                        ['mock-parent-commit'],
                        'mock-ref',
                        'mock-project-root',
                        null,
                        new Tag('mock-tag', 'mock-commit-1')
                    )
                ),
                true
            ],
        ];
    }
}
