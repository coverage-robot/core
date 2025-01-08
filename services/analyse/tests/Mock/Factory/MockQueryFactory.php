<?php

declare(strict_types=1);

namespace App\Tests\Mock\Factory;

use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class MockQueryFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function createMock(
        TestCase $testCase,
        string $queryClass,
        ?string $queryString,
        QueryResultInterface $parsedResults
    ): QueryInterface|MockObject {
        $mockQuery = $testCase->getMockBuilder($queryClass)
            ->disableOriginalConstructor()
            ->getMock();

        $mockQuery->expects($queryString !== null ? $testCase::any() : $testCase::never())
            ->method('getQuery')
            ->willReturn($queryString ?? '');

        $mockQuery->method('parseResults')
            ->willReturn($parsedResults);

        return $mockQuery;
    }
}
