<?php

namespace App\Tests\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\IntegerQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalUploadsQuery;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Iterator\ItemIterator;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;

class TotalUploadsQueryTest extends AbstractQueryTestCase
{
    public static function getExpectedQueries(): array
    {
        return [
            <<<SQL
            WITH
              uploads AS (
                SELECT
                  uploadId,
                  IF(
                    totalLines >= COUNT(*),
                    1,
                    0
                  ) as successful,
                  IF(
                    totalLines < COUNT(*),
                    1,
                    0
                  ) as pending
                FROM
                  `mock-table`
                WHERE
                  commit = "mock-commit"
                  AND repository = "mock-repository"
                  AND owner = "mock-owner"
                  AND provider = "github"
                GROUP BY
                  uploadId,
                  totalLines
              )
            SELECT
              SUM(successful) as successfulUploads,
              SUM(pending) as pendingUploads
            FROM
              uploads
            SQL
        ];
    }

    public function getQueryClass(): QueryInterface
    {
        return new TotalUploadsQuery();
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
                    'successfulUploads' => 1,
                    'pendingUploads' => 0
                ]
            ],
            [
                [
                    'successfulUploads' => 2,
                    'pendingUploads' => 1
                ]
            ],
            [
                [
                    'successfulUploads' => 10,
                    'pendingUploads' => 0
                ],
            ],
            [
                [
                    'successfulUploads' => 100,
                    'pendingUploads' => 0
                ]
            ],
            [
                [
                    'successfulUploads' => 98,
                    'pendingUploads' => 2
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
