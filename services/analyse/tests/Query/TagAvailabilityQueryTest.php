<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\QueryInterface;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
use App\Query\TagAvailabilityQuery;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\QueryResults;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

class TagAvailabilityQueryTest extends AbstractQueryTestCase
{
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
        $mockBigQueryResult = $this->createMock(QueryResults::class);
        $mockBigQueryResult->expects($this->once())
            ->method('isComplete')
            ->willReturn(true);
        $mockBigQueryResult->expects($this->once())
            ->method('rows')
            ->willReturn($queryResult);

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
                QueryParameterBag::fromWaypoint(
                    new ReportWaypoint(
                        provider: Provider::GITHUB,
                        owner: 'mock-owner',
                        repository: 'mock-repository',
                        ref: 'mock-ref',
                        commit: 'mock-commit',
                        history: [],
                        diff: []
                    )
                ),
                true
            ],
        ];
    }
}
