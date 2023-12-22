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
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Serializer;

abstract class AbstractQueryTestCase extends KernelTestCase
{
    use MatchesSnapshots;

    /**
     * Get the query class that will be tested.
     */
    abstract public function getQueryClass(): QueryInterface;

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
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: [],
                    pullRequest: 12
                )
            )
        ];
    }

    /**
     * Test parsing the results of the query into a result object.
     */
    abstract public function testParseResults(array $queryResult): void;

    /**
     * Test validating the parameters passed to the query.
     */
    abstract public function testValidateParameters(QueryParameterBag $parameters, bool $valid): void;

    #[DataProvider('queryParametersDataProvider')]
    public function testGetQuery(QueryParameterBag $parameters): void
    {
        $queryBuilder = new QueryBuilderService(
            new SqlFormatter(new NullHighlighter()),
            $this->createMock(Serializer::class)
        );

        $this->assertMatchesTextSnapshot(
            $queryBuilder->build(
                $this->getQueryClass(),
                'mock-table',
                $parameters
            )
        );
    }

    public static function queryParametersDataProvider(): array
    {
        return array_map(
            static fn(QueryParameterBag $parameters): array => [
                $parameters
            ],
            static::getQueryParameters()
        );
    }
}
