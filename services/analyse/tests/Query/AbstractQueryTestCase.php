<?php

declare(strict_types=1);

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use App\Query\Result\QueryResultIterator;
use App\Service\QueryBuilderServiceInterface;
use Google\Cloud\BigQuery\QueryResults;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Environment\Service;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractQueryTestCase extends KernelTestCase
{
    use MatchesSnapshots;

    /**
     * Get the query class that will be tested.
     *
     * @return class-string<QueryInterface>
     */
    abstract public function getQueryClass(): string;

    /**
     * Get the query parameters that will be passed to the query.
     *
     * @return QueryParameterBag[]
     */
    abstract public static function getQueryParameters(): array;

    /**
     * Get mock results in the structure(s) which the query will return.
     *
     * @return iterable[]
     */
    abstract public static function getQueryResults(): array;

    #[DataProvider('queryParametersDataProvider')]
    public function testGetQuery(QueryParameterBag $parameters): void
    {
        $container = $this->getContainer();

        $container->set(
            EnvironmentServiceInterface::class,
            MockEnvironmentServiceFactory::createMock(
                Environment::PRODUCTION,
                Service::ANALYSE,
                [
                    EnvironmentVariable::BIGQUERY_PROJECT->value => 'mock-project-id',
                    EnvironmentVariable::BIGQUERY_ENVIRONMENT_DATASET->value => 'prod',
                    EnvironmentVariable::BIGQUERY_UPLOAD_TABLE->value => 'mock-upload-table',
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-line-coverage-table'
                ]
            ),
        );

        $queryBuilder = $container->get(QueryBuilderServiceInterface::class);

        $this->assertMatchesTextSnapshot(
            $queryBuilder->build(
                $container->get($this->getQueryClass()),
                $parameters
            )
        );
    }

    #[DataProvider('queryResultsDataProvider')]
    public function testParseResults(iterable $queryResult): void
    {
        $validator = $this->getContainer()
            ->get(ValidatorInterface::class);

        $query = $this->getContainer()
            ->get($this->getQueryClass());

        $mockBigQueryResult = $this->createMock(QueryResults::class);
        $mockBigQueryResult->method('rows')
            ->willReturn($queryResult);
        $mockBigQueryResult->method('info')
            ->willReturn(['totalRows' => count($queryResult)]);

        $results = $query->parseResults($mockBigQueryResult);

        $this->assertInstanceOf(QueryResultInterface::class, $results);

        if ($results instanceof QueryResultIterator) {
            foreach ($results as $result) {
                $validator->validate($result);
            }
        } else {
            $validator->validate($results);
        }
    }

    /**
     * @return QueryParameterBag[][]
     */
    public static function queryParametersDataProvider(): array
    {
        return array_map(
            static fn(QueryParameterBag $parameters): array => [
                $parameters
            ],
            static::getQueryParameters()
        );
    }

    /**
     * @return iterable[][]
     */
    public static function queryResultsDataProvider(): array
    {
        return array_map(
            static fn(iterable $results): array => [
                $results
            ],
            static::getQueryResults()
        );
    }
}
