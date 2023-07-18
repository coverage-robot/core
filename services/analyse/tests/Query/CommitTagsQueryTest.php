<?php

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\CommitTagsQuery;
use App\Query\QueryInterface;
use App\Query\Result\CommitCollectionQueryResult;
use Google\Cloud\BigQuery\QueryResults;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;

class CommitTagsQueryTest extends AbstractQueryTestCase
{
    public function getQueryClass(): QueryInterface
    {
        return new CommitTagsQuery();
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
              ARRAY_AGG(DISTINCT tag) as tags
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
              ARRAY_AGG(DISTINCT tag) as tags
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
        $upload = Upload::from([
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'commit' => 'mock-commit',
            'uploadId' => 'mock-uploadId',
            'ref' => 'mock-ref',
            'parent' => [],
            'tag' => 'mock-tag',
        ]);

        $multipleCommitParameters = QueryParameterBag::fromUpload($upload);
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
                        'tags' => ['mock-tag']
                    ]
                ],
            ],
            [
                [
                    [
                        'commit' => 'mock-commit',
                        'tags' => ['mock-tag']
                    ],
                    [
                        'commit' => 'mock-commit-2',
                        'tags' => ['mock-tag', 'mock-tag-2']
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
                QueryParameterBag::fromUpload(
                    Upload::from([
                        'provider' => Provider::GITHUB->value,
                        'owner' => 'mock-owner',
                        'repository' => 'mock-repository',
                        'commit' => 'mock-commit',
                        'uploadId' => 'mock-uploadId',
                        'ref' => 'mock-ref',
                        'parent' => [],
                        'tag' => 'mock-tag',
                    ])
                ),
                true
            ],
        ];
    }
}
