<?php

namespace App\Tests\Mock\Factory;

use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
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
            ->disableOriginalConstructor()
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
