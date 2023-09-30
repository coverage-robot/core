<?php

namespace App\Tests\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalUploadsQuery;
use DateTime;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Iterator\ItemIterator;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
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
                  tag,
                  commit,
                  IF(
                    COUNT(uploadId) >= totalLines,
                    1,
                    0
                  ) as successful,
                  IF(
                    COUNT(uploadId) < totalLines,
                    1,
                    0
                  ) as pending,
                  ingestTime
                FROM
                  `mock-table`
                WHERE
                  commit = "mock-commit"
                  AND repository = "mock-repository"
                  AND owner = "mock-owner"
                  AND provider = "github"
                GROUP BY
                  uploadId,
                  tag,
                  commit,
                  totalLines,
                  ingestTime
              )
            SELECT
              ANY_VALUE(commit) as commit,
              ARRAY_AGG(
                IF(successful = 1, uploadId, NULL) IGNORE NULLS
              ) as successfulUploads,
              ARRAY_AGG(
                IF(successful = 1, tag, NULL) IGNORE NULLS
              ) as successfulTags,
              ARRAY_AGG(
                IF(pending = 1, uploadId, NULL) IGNORE NULLS
              ) as pendingUploads,
              MAX(
                IF(successful = 1, ingestTime, NULL)
              ) as latestSuccessfulUpload
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
                    'commit' => 'mock-commit',
                    'successfulUploads' => ['1'],
                    'successfulTags' => ['tag-1'],
                    'pendingUploads' => [],
                    'latestSuccessfulUpload' => new DateTime('2023-09-09T12:00:00+0000')
                ]
            ],
            [
                [
                    'commit' => 'mock-commit',
                    'successfulUploads' => ['1', '2'],
                    'successfulTags' => ['tag-1', 'tag-2'],
                    'pendingUploads' => ['3'],
                    'latestSuccessfulUpload' => new DateTime('2023-09-09T12:00:00+0000')
                ]
            ],
            [
                [
                    'commit' => 'mock-commit',
                    'successfulUploads' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
                    'successfulTags' => ['tag-1'],
                    'pendingUploads' => [],
                    'latestSuccessfulUpload' => new DateTime('2023-09-09T12:00:00+0000')
                ],
            ],
            [
                [
                    'commit' => 'mock-commit',
                    'successfulUploads' => ['1', '2', '3', '4', '5', '6', '7', '8'],
                    'successfulTags' => ['tag-1'],
                    'pendingUploads' => ['9', '10'],
                    'latestSuccessfulUpload' => new DateTime('2023-09-09T12:00:00+0000')
                ]
            ],
            [
                [
                    'commit' => 'mock-commit',
                    'successfulUploads' => [],
                    'successfulTags' => [],
                    'pendingUploads' => ['9', '10'],
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
