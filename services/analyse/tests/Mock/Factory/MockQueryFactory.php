<?php

namespace App\Tests\Mock\Factory;

use App\Enum\EnvironmentVariable;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use Packages\Models\Enum\Environment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockQueryFactory
{
    /**
     * @param TestCase $testCase
     * @param string $queryClass
     * @param string|null $queryString
     * @param mixed|null $parsedResults
     * @return MockObject
     */
    public static function createMock(
        TestCase $testCase,
        string $queryClass,
        ?string $queryString,
        QueryResultInterface $parsedResults
    ): QueryInterface|MockObject {
        $mockQuery = $testCase->getMockBuilder($queryClass)
            ->setConstructorArgs(
                [
                    MockEnvironmentServiceFactory::getMock(
                        $testCase,
                        Environment::TESTING,
                        [
                            EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-line-coverage-table',
                            EnvironmentVariable::BIGQUERY_UPLOAD_TABLE->value => 'mock-upload-table',
                        ]
                    )
                ]
            )
            ->onlyMethods(['getQuery', 'parseResults'])
            ->getMock();

        $mockQuery->expects($queryString !== null ? $testCase::any() : $testCase::never())
            ->method('getQuery')
            ->willReturn($queryString ?? '');

        $mockQuery->method('parseResults')
            ->willReturn($parsedResults);

        return $mockQuery;
    }
}
