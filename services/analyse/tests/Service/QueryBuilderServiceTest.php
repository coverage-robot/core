<?php

namespace App\Tests\Service;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\CoverageQueryResult;
use App\Query\TotalCoverageQuery;
use App\Service\QueryBuilderService;
use App\Tests\Mock\Factory\MockQueryFactory;
use DateTimeImmutable;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\TestCase;

class QueryBuilderServiceTest extends TestCase
{
    public function testBuildFormatsQuery(): void
    {
        $queryParameters = QueryParameterBag::fromEvent(
            new Upload(
                'mock-uploadId',
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-commit',
                [],
                'main',
                'project-root',
                12,
                new Tag('mock-tag', 'mock-commit'),
                new DateTimeImmutable('2023-09-02T10:12:00+00:00'),
            )
        );

        $queryBuilder = new QueryBuilderService(
            new SqlFormatter(
                new NullHighlighter()
            )
        );

        $this->assertEquals(
            <<<SQL
            SELECT
              *
            FROM
              `mock-table`
            WHERE
              commit = "mock-commit"
              AND provider = "github"
            SQL,
            $queryBuilder->build(
                MockQueryFactory::createMock(
                    $this,
                    TotalCoverageQuery::class,
                    'SELECT * FROM `mock-table` WHERE commit = "mock-commit" AND provider = "github"',
                    $this->createMock(CoverageQueryResult::class)
                ),
                'mock-table',
                $queryParameters
            )
        );
    }

    public function testBuildValidatesQueryParameters(): void
    {
        $queryParameters = new QueryParameterBag();

        $queryBuilder = new QueryBuilderService(
            new SqlFormatter(
                new NullHighlighter()
            )
        );

        $this->expectException(QueryException::class);

        $queryBuilder->build(
            MockQueryFactory::createMock(
                $this,
                TotalCoverageQuery::class,
                '',
                $this->createMock(CoverageQueryResult::class)
            ),
            'mock-table',
            $queryParameters
        );
    }
}
