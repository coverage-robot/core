<?php

namespace App\Tests\Query;

use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\QueryInterface;
use App\Service\QueryBuilderService;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Serializer;

abstract class AbstractQueryTestCase extends KernelTestCase
{
    abstract public function getQueryClass(): QueryInterface;

    /**
     * Get the expected SQL queries that will be generated when passed parameters.
     *
     * @return string[]
     */
    abstract public static function getExpectedQueries(): array;

    /**
     * Get the query parameters that will be passed to the query.
     *
     * @return QueryParameterBag[]
     */
    public static function getQueryParameters(): array
    {
        return [
            QueryParameterBag::fromWaypoint(
                new ReportWaypoint(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'mock-ref',
                    'mock-commit',
                    12,
                    [],
                    []
                )
            )
        ];
    }

    #[DataProvider('queryParametersAndOutputsDataProvider')]
    public function testGetQuery(string $expectedSql, QueryParameterBag $parameters): void
    {
        $queryBuilder = new QueryBuilderService(
            new SqlFormatter(new NullHighlighter()),
            $this->createMock(Serializer::class)
        );

        $query = $this->getQueryClass();

        $builtSql = $queryBuilder->build($query, 'mock-table', $parameters);

        $this->assertEquals(
            $expectedSql,
            $builtSql
        );
    }

    abstract public function testParseResults(array $queryResult): void;

    abstract public function testValidateParameters(QueryParameterBag $parameters, bool $valid): void;

    /**
     * Build an array of data which matches the expected SQL outputs against the
     * provided parameters as inputs, which can be provided to the query test.
     */
    public static function queryParametersAndOutputsDataProvider(): array
    {
        return array_map(
            static fn(string $sql, QueryParameterBag $parameters): array => [
                $sql,
                $parameters
            ],
            static::getExpectedQueries(),
            static::getQueryParameters()
        );
    }
}
