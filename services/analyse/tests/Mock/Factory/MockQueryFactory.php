<?php

namespace App\Tests\Mock\Factory;

use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class MockQueryFactory
{
    /**
     * @param string|null $queryString
     * @param mixed|null $parsedResults
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function createMock(
        TestCase $testCase,
        ContainerInterface $container,
        string $queryClass,
        ?string $queryString,
        QueryResultInterface $parsedResults,
        bool $isCacheable = false
    ): QueryInterface|MockObject {
        $mockQuery = $testCase->getMockBuilder($queryClass)
            ->disableOriginalConstructor()
            ->getMock();

        $mockQuery->expects($queryString !== null ? $testCase::any() : $testCase::never())
            ->method('getQuery')
            ->willReturn($queryString ?? '');

        $mockQuery->method('parseResults')
            ->willReturn($parsedResults);

        $mockQuery->method('isCachable')
            ->willReturn($isCacheable);

        return $mockQuery;
    }
}
