<?php

namespace App\Tests\Query;

use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

abstract class AbstractQueryTestCase extends TestCase
{
    abstract public function getQueryClass(): QueryInterface;

    /**
     * Get the expected SQL queries that will be generated when passed parameters.
     *
     * @return string[]
     */
    abstract public static function getExpectedSqls(): array;

    /**
     * Get the query parameters that will be passed to the query.
     *
     * @return QueryParameterBag[]
     */
    public static function getQueryParameters(): array
    {
        return [
            new QueryParameterBag()
        ];
    }

    #[DataProvider('queryParametersAndOutputsDataProvider')]
    public function testGetQuery(string $expectedSql, QueryParameterBag $parameters): void
    {
        $query = $this->getQueryClass();

        $builtSql = $query->getQuery(
            'mock-table',
            Upload::from([
                'provider' => Provider::GITHUB->value,
                'owner' => 'mock-owner',
                'repository' => 'mock-repository',
                'commit' => 'mock-commit',
                'uploadId' => 'mock-uploadId',
                'ref' => 'mock-ref',
                'parent' => [],
                'tag' => 'mock-tag',
            ]),
            $parameters
        );

        $this->assertEquals(
            $expectedSql,
            $builtSql
        );
    }

    /**
     * Build an array of data which matches the expectedSQL outputs against the
     * provided parameters as inputs, which can be provided to the query test.
     */
    public static function queryParametersAndOutputsDataProvider(): array
    {
        return array_map(
            static fn(string $sql, QueryParameterBag $parameters): array => [
                $sql,
                $parameters
            ],
            static::getExpectedSqls(),
            static::getQueryParameters()
        );
    }
}
