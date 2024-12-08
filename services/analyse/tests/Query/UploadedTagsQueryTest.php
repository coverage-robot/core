<?php

declare(strict_types=1);

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\QueryInterface;
use App\Query\Result\UploadedTagsCollectionQueryResult;
use App\Query\UploadedTagsQuery;
use Google\Cloud\BigQuery\QueryResults;
use Iterator;
use Override;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\Service;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

final class UploadedTagsQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): QueryInterface
    {
        return new UploadedTagsQuery(
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::createMock(
                Environment::PRODUCTION,
                Service::ANALYSE,
                [
                    EnvironmentVariable::BIGQUERY_UPLOAD_TABLE->value => 'mock-table'
                ]
            )
        );
    }

    #[DataProvider('resultsDataProvider')]
    #[Override]
    public function testParseResults(array $queryResult): void
    {
        $mockBigQueryResult = $this->createMock(QueryResults::class);
        $mockBigQueryResult->expects($this->once())
            ->method('rows')
            ->willReturn($queryResult);

        $result = $this->getQueryClass()
            ->parseResults($mockBigQueryResult);

        $this->assertInstanceOf(UploadedTagsCollectionQueryResult::class, $result);
    }

    #[DataProvider('parametersDataProvider')]
    #[Override]
    public function testValidateParameters(QueryParameterBag $parameters, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(QueryException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        $this->getQueryClass()->validateParameters($parameters);
    }

    #[Override]
    public static function getQueryParameters(): array
    {
        return [
            QueryParameterBag::fromWaypoint(
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                )
            )
        ];
    }

    public static function resultsDataProvider(): Iterator
    {
        yield [
            []
        ];
        yield [
            [
                ['tagName' => 'mock-tag-1'],
                ['tagName' => 'mock-tag-2'],
                ['tagName' => 'mock-tag-3'],
                ['tagName' => 'mock-tag-4'],
            ]
        ];
    }

    public static function parametersDataProvider(): Iterator
    {
        yield [
            new QueryParameterBag(),
            false
        ];
        yield [
            QueryParameterBag::fromWaypoint(
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: 'mock-project',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                )
            ),
            true
        ];
    }
}
