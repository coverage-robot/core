<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
use App\Query\TagAvailabilityQuery;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Iterator\ItemIterator;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

class TagAvailabilityQueryTest extends AbstractQueryTestCase
{
    public static function getExpectedQueries(): array
    {
        return [
            <<<SQL
            SELECT
              tag as tagName,
              ARRAY_AGG(commit) as availableCommits,
            FROM
              `mock-table`
            WHERE
              commit = "mock-commit"
              AND repository = "mock-repository"
              AND owner = "mock-owner"
              AND provider = "github"
            GROUP BY
              tag
            SQL
        ];
    }

    public function getQueryClass(): QueryInterface
    {
        return new TagAvailabilityQuery(
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

        $this->assertInstanceOf(TagAvailabilityCollectionQueryResult::class, $result);
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
                        'tagName' => 'mock-tag',
                        'availableCommits' => ['mock-commit-1', 'mock-commit-2']
                    ]
                ]
            ],
            [
                [
                    [
                        'tagName' => 'mock-tag',
                        'availableCommits' => ['mock-commit-1', 'mock-commit-2']
                    ],
                    [
                        'tagName' => 'mock-tag-2',
                        'availableCommits' => ['mock-commit-3', 'mock-commit-4']
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
