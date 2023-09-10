<?php

namespace App\Tests\Service;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\CoverageQueryResult;
use App\Query\TotalCoverageQuery;
use App\Service\QueryBuilderService;
use App\Tests\Mock\Factory\MockQueryFactory;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use PHPUnit\Framework\TestCase;

class QueryBuilderServiceTest extends TestCase
{
    public function testBuildFormatsQuery(): void
    {
        $queryParameters = QueryParameterBag::fromUpload(
            Upload::from(
                [
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'commit' => 'mock-commit',
                    'uploadId' => 'mock-uploadId',
                    'ref' => 'mock-ref',
                    'parent' => [],
                    'tag' => 'mock-tag',
                ]
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
