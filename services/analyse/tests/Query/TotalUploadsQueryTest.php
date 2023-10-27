<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalUploadsQuery;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Iterator\ItemIterator;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

class TotalUploadsQueryTest extends AbstractQueryTestCase
{
    public static function getExpectedQueries(): array
    {
        return [
            <<<SQL
            SELECT
              COALESCE(
                ARRAY_AGG(uploadId),
                []
              ) as successfulUploads,
              COALESCE(
                ARRAY_AGG(
                  STRUCT(
                    tag as name, "mock-commit" as commit
                  )
                ),
                []
              ) as successfulTags,
              COALESCE(
                MAX(ingestTime),
                NULL
              ) as latestSuccessfulUpload
            FROM
              `mock-table`
            WHERE
              commit = "mock-commit"
              AND repository = "mock-repository"
              AND owner = "mock-owner"
              AND provider = "github"
            SQL
        ];
    }

    public function getQueryClass(): QueryInterface
    {
        return new TotalUploadsQuery(
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

    #[DataProvider('resultsDataProvider')]
    public function testParseResults(array $queryResult): void
    {
        $mockIterator = $this->createMock(ItemIterator::class);
        $mockIterator->expects($this->once())
            ->method('current')
            ->willReturn($queryResult);

        $mockBigQueryResult = $this->createMock(QueryResults::class);
        $mockBigQueryResult->expects($this->once())
            ->method('isComplete')
            ->willReturn(true);
        $mockBigQueryResult->expects($this->once())
            ->method('rows')
            ->willReturn($mockIterator);

        $result = $this->getQueryClass()
            ->parseResults($mockBigQueryResult);

        $this->assertInstanceOf(TotalUploadsQueryResult::class, $result);
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
                    'successfulUploads' => ['1'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'mock-commit'
                        ]
                    ],
                    'latestSuccessfulUpload' => '2023-09-09T12:00:00+0000'
                ]
            ],
            [
                [
                    'successfulUploads' => ['1', '2'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'mock-commit'
                        ],
                        [
                            'name' => 'tag-2',
                            'commit' => 'mock-commit'
                        ]
                    ],
                    'latestSuccessfulUpload' => '2023-09-09T12:00:00+0000'
                ]
            ],
            [
                [
                    'successfulUploads' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'mock-commit'
                        ]
                    ],
                    'latestSuccessfulUpload' => '2023-09-09T12:00:00+0000'
                ],
            ],
            [
                [
                    'successfulUploads' => ['1', '2', '3', '4', '5', '6', '7', '8'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'mock-commit'
                        ]
                    ],
                    'latestSuccessfulUpload' => '2023-09-09T12:00:00+0000'
                ]
            ],
            [
                [
                    'commit' => 'mock-commit',
                    'successfulUploads' => [],
                    'successfulTags' => [],
                    'latestSuccessfulUpload' => null
                ]
            ],
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
