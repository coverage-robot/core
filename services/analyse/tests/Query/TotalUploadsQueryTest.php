<?php

namespace App\Tests\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
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
              ARRAY_AGG(
                IF(successful = 1, uploadId, NULL) IGNORE NULLS
              ) as successfulUploads,
              ARRAY_AGG(
                IF(pending = 1, uploadId, NULL) IGNORE NULLS
              ) as pendingUploads
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
                    'successfulUploads' => ["1"],
                    'pendingUploads' => []
                ]
            ],
            [
                [
                    'successfulUploads' => ["1", "2"],
                    'pendingUploads' => ["3"]
                ]
            ],
            [
                [
                    'successfulUploads' => ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10"],
                    'pendingUploads' => []
                ],
            ],
            [
                [
                    'successfulUploads' => ["1", "2", "3", "4", "5", "6", "7", "8"],
                    'pendingUploads' => ["9", "10"]
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
